<?php
/**
 * ===================================================================
 *  AVIDMOCK . WeaknessAnalyzer
 *  AI-powered skill-gap engine.
 *
 *  Analyses every quiz_attempt_answer for a student, groups by SAT
 *  math domain & sub-skill, detects trends, calls Claude for a
 *  natural-language coaching report, and returns structured data
 *  for the Weakness Analyzer dashboard.
 *
 *  Caches results in `weakness_analyses` (refreshed weekly or on
 *  demand via POST action=refresh).
 *
 *  Public API:
 *    WeaknessAnalyzer::analyse(int $userId, bool $forceRefresh)
 *      => [ weak_skills, strong_skills, ai_analysis,
 *           recommended_quizzes, predicted_score_impact,
 *           domain_scores, readiness_score, generated_at ]
 * ===================================================================
 */

class WeaknessAnalyzer
{
    /* ----------------------------------------------------------------
     *  SAT Math domain map
     *  domain slug => [ display label, sub-skills[] ]
     * ---------------------------------------------------------------- */
    private const DOMAINS = [
        'algebra' => [
            'label'  => 'Algebra',
            'skills' => [
                'linear_equations'      => 'Linear Equations',
                'linear_inequalities'   => 'Linear Inequalities',
                'systems_of_equations'  => 'Systems of Equations',
                'absolute_value'        => 'Absolute Value',
                'word_problems_linear'  => 'Word Problems (Linear)',
                'linear_functions'      => 'Linear Functions',
                'slope_intercept'       => 'Slope & Intercept',
            ],
        ],
        'advanced_math' => [
            'label'  => 'Advanced Math',
            'skills' => [
                'quadratic_equations'   => 'Quadratic Equations',
                'polynomial_operations' => 'Polynomial Operations',
                'exponential_functions' => 'Exponential Functions',
                'radical_expressions'   => 'Radical Expressions',
                'rational_expressions'  => 'Rational Expressions',
                'nonlinear_functions'   => 'Nonlinear Functions',
                'factoring'             => 'Factoring',
            ],
        ],
        'problem_solving' => [
            'label'  => 'Problem Solving & Data Analysis',
            'skills' => [
                'ratios_proportions'    => 'Ratios & Proportions',
                'percentages'           => 'Percentages',
                'unit_conversion'       => 'Unit Conversion',
                'scatterplots'          => 'Scatterplots',
                'linear_exponential_growth' => 'Linear & Exponential Growth',
                'probability'           => 'Probability',
                'statistics'            => 'Statistics (Mean, Median, Mode)',
                'data_interpretation'   => 'Data Interpretation',
            ],
        ],
        'geometry' => [
            'label'  => 'Geometry & Trigonometry',
            'skills' => [
                'area_volume'           => 'Area & Volume',
                'triangles'             => 'Triangles',
                'circles'               => 'Circles',
                'coordinate_geometry'   => 'Coordinate Geometry',
                'right_triangle_trig'   => 'Right Triangle Trig',
                'angles_lines'          => 'Angles & Lines',
                'unit_circle'           => 'Unit Circle & Radians',
            ],
        ],
    ];

    /* ================================================================
     *  PUBLIC  analyse()
     * ================================================================ */
    public static function analyse(int $userId, bool $forceRefresh = false): array
    {
        self::ensureTable();

        /* --- check cache ------------------------------------------ */
        if (!$forceRefresh) {
            $cached = self::getCached($userId);
            if ($cached) {
                return $cached;
            }
        }

        /* --- build fresh analysis --------------------------------- */
        $raw        = self::fetchRawAnswers($userId);
        $skillStats = self::aggregateBySkill($raw);
        $domainScores = self::computeDomainScores($skillStats);
        $readiness  = self::computeReadiness($domainScores);

        $weakSkills   = self::extractWeakSkills($skillStats);
        $strongSkills = self::extractStrongSkills($skillStats);

        $recommendedQuizzes = self::recommendQuizzes($userId, $weakSkills);
        $scoreImpact        = self::predictScoreImpact($weakSkills, $domainScores);

        /* --- AI analysis ----------------------------------------- */
        $aiAnalysis = self::generateAIAnalysis(
            $userId, $weakSkills, $strongSkills, $domainScores, $readiness, $scoreImpact
        );

        /* --- attach AI tips to weak skills ----------------------- */
        $weakSkills = self::attachSkillTips($weakSkills, $aiAnalysis);

        /* --- assemble result ------------------------------------- */
        $result = [
            'weak_skills'            => $weakSkills,
            'strong_skills'          => $strongSkills,
            'ai_analysis'            => $aiAnalysis,
            'recommended_quizzes'    => $recommendedQuizzes,
            'predicted_score_impact' => $scoreImpact,
            'domain_scores'          => $domainScores,
            'readiness_score'        => $readiness,
            'generated_at'           => date('Y-m-d H:i:s'),
            'total_answers'          => count($raw),
        ];

        /* --- persist cache --------------------------------------- */
        self::saveCache($userId, $result);

        return $result;
    }

    /* ================================================================
     *  DATA RETRIEVAL
     * ================================================================ */

    /**
     * Pull every answer this student has ever given across all quiz attempts.
     * Joins with sat_quiz_questions for domain + skill metadata.
     */
    private static function fetchRawAnswers(int $userId): array
    {
        return Database::fetchAll(
            "SELECT
                qaa.question_id,
                qaa.given_answer,
                qaa.is_correct,
                qaa.time_spent,
                qaa.answered_at,
                q.domain,
                q.skill,
                q.difficulty,
                q.correct_answer,
                a.quiz_id
             FROM quiz_attempt_answers qaa
             JOIN sat_quiz_attempts a ON a.id = qaa.attempt_id
             JOIN sat_quiz_questions q ON q.id = qaa.question_id
             WHERE a.user_id = ?
             ORDER BY qaa.answered_at ASC",
            [$userId]
        );
    }

    /* ================================================================
     *  AGGREGATION
     * ================================================================ */

    /**
     * Groups answers by (domain, skill) and computes per-skill:
     *   total, correct, accuracy, avg_time, trend, difficulty_breakdown
     */
    private static function aggregateBySkill(array $answers): array
    {
        $buckets = []; // domain => skill => [ answers[] ]

        foreach ($answers as $row) {
            $domain = self::normaliseDomain($row['domain'] ?? '');
            $skill  = self::normaliseSkill($row['skill'] ?? '', $domain);
            if (!$domain || !$skill) continue;

            $buckets[$domain][$skill][] = $row;
        }

        $stats = [];
        foreach ($buckets as $domain => $skills) {
            foreach ($skills as $skill => $rows) {
                $total   = count($rows);
                $correct = 0;
                $times   = [];
                foreach ($rows as $r) {
                    if (!empty($r['is_correct'])) $correct++;
                    if ((int)($r['time_spent'] ?? 0) > 0) $times[] = (int)$r['time_spent'];
                }
                $accuracy = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
                $avgTime  = count($times) > 0 ? round(array_sum($times) / count($times)) : 0;

                /* trend: compare first-half accuracy vs second-half */
                $trend = self::computeTrend($rows);

                $domainLabel = self::DOMAINS[$domain]['label'] ?? ucfirst(str_replace('_', ' ', $domain));
                $skillLabel  = self::DOMAINS[$domain]['skills'][$skill]
                    ?? ucwords(str_replace('_', ' ', $skill));

                $stats[] = [
                    'domain'       => $domain,
                    'domain_label' => $domainLabel,
                    'skill'        => $skill,
                    'skill_label'  => $skillLabel,
                    'total'        => $total,
                    'correct'      => $correct,
                    'accuracy'     => $accuracy,
                    'avg_time'     => $avgTime,
                    'trend'        => $trend,      // 'improving' | 'declining' | 'stagnant'
                ];
            }
        }

        /* Sort by accuracy ascending (weakest first) */
        usort($stats, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        return $stats;
    }

    private static function computeTrend(array $rows): string
    {
        $n = count($rows);
        if ($n < 4) return 'stagnant';

        $mid = (int) floor($n / 2);
        $firstHalf  = array_slice($rows, 0, $mid);
        $secondHalf = array_slice($rows, $mid);

        $acc1 = self::sliceAccuracy($firstHalf);
        $acc2 = self::sliceAccuracy($secondHalf);

        $diff = $acc2 - $acc1;
        if ($diff > 8)  return 'improving';
        if ($diff < -8) return 'declining';
        return 'stagnant';
    }

    private static function sliceAccuracy(array $rows): float
    {
        if (empty($rows)) return 0;
        $c = 0;
        foreach ($rows as $r) { if (!empty($r['is_correct'])) $c++; }
        return ($c / count($rows)) * 100;
    }

    /* ================================================================
     *  DOMAIN SCORES
     * ================================================================ */

    private static function computeDomainScores(array $skillStats): array
    {
        $domains = [];
        foreach (self::DOMAINS as $slug => $meta) {
            $domains[$slug] = [
                'label'    => $meta['label'],
                'accuracy' => 0,
                'total'    => 0,
                'correct'  => 0,
            ];
        }

        foreach ($skillStats as $s) {
            $d = $s['domain'];
            if (!isset($domains[$d])) continue;
            $domains[$d]['total']   += $s['total'];
            $domains[$d]['correct'] += $s['correct'];
        }

        foreach ($domains as $slug => &$d) {
            $d['accuracy'] = $d['total'] > 0
                ? round(($d['correct'] / $d['total']) * 100, 1)
                : 0;
        }
        unset($d);

        return $domains;
    }

    private static function computeReadiness(array $domainScores): int
    {
        $vals = array_filter(array_column($domainScores, 'accuracy'), fn($v) => $v > 0);
        if (empty($vals)) return 0;
        return (int) round(array_sum($vals) / count($vals));
    }

    /* ================================================================
     *  WEAK / STRONG EXTRACTION
     * ================================================================ */

    private static function extractWeakSkills(array $skillStats): array
    {
        $weak = [];
        foreach ($skillStats as $s) {
            if ($s['total'] < 2) continue; // need minimum data
            if ($s['accuracy'] < 70) {
                $s['tip'] = ''; // will be filled by AI
                $weak[] = $s;
            }
        }
        return array_slice($weak, 0, 8); // cap at 8
    }

    private static function extractStrongSkills(array $skillStats): array
    {
        $strong = [];
        foreach ($skillStats as $s) {
            if ($s['total'] < 2) continue;
            if ($s['accuracy'] >= 80) {
                $strong[] = $s;
            }
        }
        /* Sort strongest first */
        usort($strong, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);
        return array_slice($strong, 0, 6);
    }

    /* ================================================================
     *  QUIZ RECOMMENDATIONS
     * ================================================================ */

    private static function recommendQuizzes(int $userId, array $weakSkills): array
    {
        if (empty($weakSkills)) return [];

        $domains = array_unique(array_column($weakSkills, 'domain'));
        if (empty($domains)) return [];

        $placeholders = implode(',', array_fill(0, count($domains), '?'));

        try {
            $quizzes = Database::fetchAll(
                "SELECT q.id, q.title, q.lesson_slug, q.category
                 FROM sat_quizzes q
                 WHERE q.category IN ({$placeholders})
                   AND q.id NOT IN (
                       SELECT DISTINCT a.quiz_id
                       FROM sat_quiz_attempts a
                       WHERE a.user_id = ? AND a.status = 'completed'
                         AND a.score >= 80
                   )
                 ORDER BY RAND()
                 LIMIT 5",
                [...$domains, $userId]
            );
        } catch (\Throwable $e) {
            error_log('WeaknessAnalyzer::recommendQuizzes: ' . $e->getMessage());
            $quizzes = [];
        }

        return array_map(fn($q) => [
            'id'          => (int) $q['id'],
            'title'       => $q['title'] ?? 'Practice Quiz',
            'lesson_slug' => $q['lesson_slug'] ?? '',
            'category'    => $q['category'] ?? '',
        ], $quizzes);
    }

    /* ================================================================
     *  SCORE IMPACT PREDICTION
     * ================================================================ */

    private static function predictScoreImpact(array $weakSkills, array $domainScores): array
    {
        if (empty($weakSkills)) {
            return ['points' => 0, 'description' => 'No weak areas detected -- you are on track!'];
        }

        /*
         * Rough model:
         *   Each SAT math question is worth ~12 scaled points.
         *   If you raise a weak skill from X% to 85%, the delta in
         *   expected-correct questions gives estimated point gain.
         *
         *   SAT Math section: 44 questions, 200-800 scale (600 pt range).
         *   Points per question ~ 600/44 ~ 13.6
         */
        $totalDelta = 0;
        foreach ($weakSkills as $s) {
            $currentAcc = $s['accuracy'] / 100;
            $targetAcc  = 0.85;
            $questionPool = max($s['total'], 3);
            $extraCorrect = ($targetAcc - $currentAcc) * $questionPool;
            $totalDelta  += $extraCorrect;
        }

        $pointsPerQuestion = 13.6;
        $estimatedGain = (int) round($totalDelta * $pointsPerQuestion);
        $estimatedGain = max(0, min($estimatedGain, 200)); // clamp to reasonable range

        $desc = $estimatedGain > 0
            ? "If you master these weak areas, your predicted SAT Math score could increase by approximately {$estimatedGain} points."
            : "Keep practicing consistently to maintain your strong performance.";

        return [
            'points'      => $estimatedGain,
            'description' => $desc,
        ];
    }

    /* ================================================================
     *  AI ANALYSIS  (Claude API)
     * ================================================================ */

    private static function generateAIAnalysis(
        int   $userId,
        array $weakSkills,
        array $strongSkills,
        array $domainScores,
        int   $readiness,
        array $scoreImpact
    ): array {
        /* --- Build the prompt ------------------------------------ */
        $userName    = 'Student';
        $targetScore = 1200;
        $testDate    = null;
        try {
            $user = Database::fetch("SELECT first_name, target_score, test_date FROM users WHERE id = ? LIMIT 1", [$userId]);
            if ($user) {
                $userName    = trim($user['first_name'] ?? 'Student') ?: 'Student';
                $targetScore = (int) ($user['target_score'] ?? 1200);
                $testDate    = $user['test_date'] ?? null;
            }
        } catch (\Throwable) {}

        $weakSummary = '';
        foreach ($weakSkills as $i => $s) {
            $n = $i + 1;
            $weakSummary .= "  {$n}. {$s['skill_label']} ({$s['domain_label']}): {$s['accuracy']}% accuracy, {$s['total']} attempts, trend={$s['trend']}, avg {$s['avg_time']}s/question\n";
        }
        $strongSummary = '';
        foreach ($strongSkills as $i => $s) {
            $n = $i + 1;
            $strongSummary .= "  {$n}. {$s['skill_label']} ({$s['domain_label']}): {$s['accuracy']}% accuracy\n";
        }
        $domainSummary = '';
        foreach ($domainScores as $slug => $d) {
            $domainSummary .= "  - {$d['label']}: {$d['accuracy']}% ({$d['total']} questions)\n";
        }

        $prompt = <<<PROMPT
You are an elite SAT math coach analysing a student's performance data. Write a personalised coaching report.

Student: {$userName}
Target Score: {$targetScore}
Test Date: {$testDate}
Overall Readiness: {$readiness}%
Potential Score Gain: {$scoreImpact['points']} points

Domain Breakdown:
{$domainSummary}

Weak Skills:
{$weakSummary}

Strong Skills:
{$strongSummary}

Return a JSON object (no markdown fences, just raw JSON) with exactly these keys:
{
  "coaching_paragraph": "A 3-5 sentence personalised coaching analysis of their unique learning pattern, what's holding them back, and an encouraging message. Address them by first name. Be specific, not generic.",
  "action_plan": [
    {"step": 1, "title": "...", "description": "...", "time_estimate": "... minutes"},
    {"step": 2, "title": "...", "description": "...", "time_estimate": "... minutes"},
    {"step": 3, "title": "...", "description": "...", "time_estimate": "... minutes"}
  ],
  "skill_tips": {
    "<skill_slug>": "One sentence actionable tip for improving this specific weak skill",
    ...
  }
}

For skill_tips, include one entry for each weak skill listed above using the exact skill slug.
Keep tips concrete and SAT-specific (e.g., "Try backsolving on quadratic equations: plug each answer choice into the original equation").
For the action plan, be very specific about WHAT to practice, HOW to practice it, and give realistic time estimates.
PROMPT;

        /* --- Call Claude ----------------------------------------- */
        try {
            $apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
            if (empty($apiKey)) {
                return self::fallbackAnalysis($userName, $weakSkills, $strongSkills, $readiness);
            }

            $model = defined('ANTHROPIC_MODEL') ? ANTHROPIC_MODEL : 'claude-sonnet-4-5-20250929';

            $payload = json_encode([
                'model'       => $model,
                'max_tokens'  => 1200,
                'temperature' => 0.5,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                error_log("WeaknessAnalyzer Claude API error: HTTP {$httpCode}");
                return self::fallbackAnalysis($userName, $weakSkills, $strongSkills, $readiness);
            }

            $decoded = json_decode($response, true);
            $text    = $decoded['content'][0]['text'] ?? '';

            /* Strip any markdown fences */
            $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
            $text = preg_replace('/\s*```\s*$/m', '', $text);

            $aiData = json_decode(trim($text), true);
            if (!is_array($aiData) || empty($aiData['coaching_paragraph'])) {
                error_log("WeaknessAnalyzer: Could not parse Claude response");
                return self::fallbackAnalysis($userName, $weakSkills, $strongSkills, $readiness);
            }

            return [
                'coaching_paragraph' => $aiData['coaching_paragraph'],
                'action_plan'        => $aiData['action_plan'] ?? [],
                'skill_tips'         => $aiData['skill_tips'] ?? [],
            ];

        } catch (\Throwable $e) {
            error_log('WeaknessAnalyzer AI error: ' . $e->getMessage());
            return self::fallbackAnalysis($userName, $weakSkills, $strongSkills, $readiness);
        }
    }

    /**
     * Deterministic fallback when Claude is unavailable.
     */
    private static function fallbackAnalysis(string $name, array $weak, array $strong, int $readiness): array
    {
        $weakNames = array_map(fn($s) => $s['skill_label'], array_slice($weak, 0, 3));
        $strongNames = array_map(fn($s) => $s['skill_label'], array_slice($strong, 0, 2));

        $coaching = "{$name}, your overall readiness is at {$readiness}%.";
        if (!empty($weakNames)) {
            $coaching .= " Your biggest opportunities for improvement are in " . implode(', ', $weakNames) . ".";
        }
        if (!empty($strongNames)) {
            $coaching .= " You're doing great with " . implode(' and ', $strongNames) . " -- keep that up!";
        }
        $coaching .= " Focused practice on your weak spots could significantly boost your SAT score.";

        $tips = [];
        foreach ($weak as $s) {
            $tips[$s['skill']] = "Practice more {$s['skill_label']} problems, focusing on understanding the underlying concepts.";
        }

        return [
            'coaching_paragraph' => $coaching,
            'action_plan'        => [
                ['step' => 1, 'title' => 'Target Your Weakest Skill',   'description' => 'Start with ' . ($weakNames[0] ?? 'your weakest topic') . '. Do 10 focused practice questions.', 'time_estimate' => '25 minutes'],
                ['step' => 2, 'title' => 'Review Mistakes',             'description' => 'Go through every wrong answer from your last 3 quizzes. Write down why you got each one wrong.', 'time_estimate' => '20 minutes'],
                ['step' => 3, 'title' => 'Mixed Practice',              'description' => 'Take a mixed-topic quiz to build pattern recognition across all domains.', 'time_estimate' => '30 minutes'],
            ],
            'skill_tips' => $tips,
        ];
    }

    /**
     * Merge AI-generated per-skill tips into the weak_skills array.
     */
    private static function attachSkillTips(array $weakSkills, array $aiAnalysis): array
    {
        $tips = $aiAnalysis['skill_tips'] ?? [];
        foreach ($weakSkills as &$s) {
            $s['tip'] = $tips[$s['skill']] ?? "Focus on {$s['skill_label']} -- practice 5 questions daily.";
        }
        unset($s);
        return $weakSkills;
    }

    /* ================================================================
     *  DOMAIN / SKILL NORMALISATION
     * ================================================================ */

    private static function normaliseDomain(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (!$raw) return '';

        $map = [
            'algebra'                          => 'algebra',
            'heart of algebra'                 => 'algebra',
            'linear equations'                 => 'algebra',
            'advanced math'                    => 'advanced_math',
            'advanced_math'                    => 'advanced_math',
            'passport to advanced math'        => 'advanced_math',
            'problem solving'                  => 'problem_solving',
            'problem_solving'                  => 'problem_solving',
            'problem solving and data analysis'=> 'problem_solving',
            'problem solving & data analysis'  => 'problem_solving',
            'data analysis'                    => 'problem_solving',
            'geometry'                         => 'geometry',
            'geometry and trigonometry'         => 'geometry',
            'geometry & trigonometry'           => 'geometry',
            'trigonometry'                      => 'geometry',
            'additional topics'                 => 'geometry',
        ];

        foreach ($map as $pattern => $slug) {
            if ($raw === $pattern || str_contains($raw, $pattern)) {
                return $slug;
            }
        }

        /* Try a fuzzy match on the canonical keys */
        foreach (self::DOMAINS as $slug => $meta) {
            if (str_contains($raw, $slug) || str_contains($slug, $raw)) {
                return $slug;
            }
        }

        return '';
    }

    private static function normaliseSkill(string $raw, string $domain): string
    {
        $raw = strtolower(trim($raw));
        if (!$raw || !$domain) return $raw ?: 'general';

        /* Exact match against known skills */
        $known = self::DOMAINS[$domain]['skills'] ?? [];
        if (isset($known[$raw])) return $raw;

        /* Normalise common variants */
        $normalised = str_replace([' ', '-', '&', 'and'], ['_', '_', '', ''], $raw);
        $normalised = preg_replace('/[^a-z0-9_]/', '', $normalised);
        $normalised = preg_replace('/_+/', '_', trim($normalised, '_'));

        if (isset($known[$normalised])) return $normalised;

        /* Substring match */
        foreach ($known as $slug => $label) {
            $labelLower = strtolower($label);
            if (str_contains($normalised, $slug) || str_contains($slug, $normalised)
                || str_contains($raw, $labelLower) || str_contains($labelLower, $raw)) {
                return $slug;
            }
        }

        return $normalised ?: 'general';
    }

    /* ================================================================
     *  CACHING  (weakness_analyses table)
     * ================================================================ */

    private static function ensureTable(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            Database::connect()->exec("
                CREATE TABLE IF NOT EXISTS `weakness_analyses` (
                    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `user_id`    INT UNSIGNED NOT NULL,
                    `data_json`  MEDIUMTEXT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `expires_at` DATETIME NOT NULL,
                    UNIQUE KEY `uq_user` (`user_id`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            error_log('WeaknessAnalyzer ensureTable: ' . $e->getMessage());
        }
    }

    private static function getCached(int $userId): ?array
    {
        try {
            $row = Database::fetch(
                "SELECT data_json, created_at, expires_at
                 FROM weakness_analyses
                 WHERE user_id = ? AND expires_at > NOW()
                 LIMIT 1",
                [$userId]
            );
            if ($row && !empty($row['data_json'])) {
                $data = json_decode($row['data_json'], true);
                if (is_array($data)) {
                    $data['cached']       = true;
                    $data['generated_at'] = $row['created_at'];
                    return $data;
                }
            }
        } catch (\Throwable $e) {
            error_log('WeaknessAnalyzer getCached: ' . $e->getMessage());
        }
        return null;
    }

    private static function saveCache(int $userId, array $data): void
    {
        try {
            $json    = json_encode($data, JSON_UNESCAPED_UNICODE);
            $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $now     = date('Y-m-d H:i:s');

            Database::connect()->prepare("
                INSERT INTO weakness_analyses (user_id, data_json, created_at, expires_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE data_json = VALUES(data_json),
                                        created_at = VALUES(created_at),
                                        expires_at = VALUES(expires_at)
            ")->execute([$userId, $json, $now, $expires]);
        } catch (\Throwable $e) {
            error_log('WeaknessAnalyzer saveCache: ' . $e->getMessage());
        }
    }

    /* ================================================================
     *  STATIC ACCESSORS (for external use)
     * ================================================================ */

    public static function getDomains(): array
    {
        return self::DOMAINS;
    }
}