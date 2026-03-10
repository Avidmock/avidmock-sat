<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AIEssayScorer
 *  Claude-powered scoring for SAT Reading & Writing responses.
 *  Provides rubric-based feedback, grammar analysis, and
 *  targeted improvement suggestions.
 * ═══════════════════════════════════════════════════════════════════
 *
 * Tables:
 *   rw_submissions (id, user_id, prompt_id, response_text TEXT,
 *     score_json TEXT, overall_score TINYINT, feedback_json TEXT,
 *     word_count INT, time_spent INT, created_at TIMESTAMP)
 *
 *   rw_prompts (id, title, passage TEXT, question TEXT,
 *     type ENUM('grammar','rhetoric','synthesis','inference'),
 *     difficulty VARCHAR(20), domain VARCHAR(50), created_at TIMESTAMP)
 */

class AIEssayScorer
{
    private const ANTHROPIC_API = 'https://api.anthropic.com/v1/messages';

    /**
     * SAT R&W question types with scoring rubrics.
     */
    public const QUESTION_TYPES = [
        'grammar' => [
            'label'   => 'Standard English Conventions',
            'skills'  => [
                'subject-verb-agreement',
                'pronoun-antecedent',
                'verb-tense-form',
                'punctuation',
                'sentence-structure',
                'modifier-placement',
                'possessives',
            ],
        ],
        'rhetoric' => [
            'label'   => 'Expression of Ideas',
            'skills'  => [
                'transitions',
                'conciseness',
                'sentence-combining',
                'rhetorical-synthesis',
                'topic-sentences',
            ],
        ],
        'comprehension' => [
            'label'   => 'Information & Ideas',
            'skills'  => [
                'central-ideas',
                'command-of-evidence',
                'inferences',
                'text-structure',
                'cross-text-connections',
            ],
        ],
        'vocabulary' => [
            'label'   => 'Craft and Structure',
            'skills'  => [
                'words-in-context',
                'text-purpose',
                'claims-counterclaims',
                'data-interpretation',
            ],
        ],
    ];

    /**
     * Score a student's R&W response using Claude.
     */
    public static function score(int $userId, string $passage, string $question, string $response, string $type = 'grammar'): array
    {
        $systemPrompt = self::buildScoringPrompt($type);
        $userPrompt = self::buildUserPrompt($passage, $question, $response, $type);

        $result = self::callClaude($systemPrompt, $userPrompt);

        if (!$result) {
            return ['error' => 'Scoring failed — please try again'];
        }

        // Save submission
        self::saveSubmission($userId, $response, $result, strlen($response));

        // Award XP
        try {
            if (class_exists('XPSystem')) {
                XPSystem::award($userId, defined('XP_WRITING_SUBMIT') ? XP_WRITING_SUBMIT : 30, 'rw_submission');
            }
        } catch (\Throwable) {}

        return $result;
    }

    /**
     * Analyze a student's writing patterns across submissions.
     */
    public static function getPatternAnalysis(int $userId): array
    {
        $db = Database::connect();
        try {
            $submissions = $db->prepare(
                "SELECT score_json, feedback_json, overall_score, created_at
                 FROM rw_submissions WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT 20"
            );
            $submissions->execute([$userId]);
            $rows = $submissions->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) return ['submissions' => 0, 'patterns' => []];

            $scores = [];
            $weakAreas = [];

            foreach ($rows as $r) {
                $score = json_decode($r['score_json'] ?? '{}', true);
                $feedback = json_decode($r['feedback_json'] ?? '{}', true);
                $scores[] = (int)$r['overall_score'];

                foreach ($feedback['weak_areas'] ?? [] as $area) {
                    $weakAreas[$area] = ($weakAreas[$area] ?? 0) + 1;
                }
            }

            arsort($weakAreas);

            return [
                'submissions'   => count($rows),
                'avg_score'     => count($scores) ? round(array_sum($scores) / count($scores), 1) : 0,
                'score_trend'   => self::calculateTrend($scores),
                'recurring_issues' => array_slice($weakAreas, 0, 5, true),
                'latest_score'  => $scores[0] ?? 0,
            ];
        } catch (\Throwable) {
            return ['submissions' => 0, 'patterns' => []];
        }
    }

    /**
     * Generate a practice prompt for a specific R&W skill.
     */
    public static function generatePrompt(string $type, string $skill, string $difficulty = 'medium'): ?array
    {
        $typeLabel = self::QUESTION_TYPES[$type]['label'] ?? $type;

        $system = "You are an SAT Reading & Writing question writer. Create an authentic Digital SAT R&W question.";
        $user = "Create a {$difficulty} difficulty SAT R&W question.\nType: {$typeLabel}\nSkill: {$skill}\n\n"
            . "Return JSON: {\"title\": \"...\", \"passage\": \"A short passage (50-150 words)\", "
            . "\"question\": \"The question text\", \"options\": {\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"}, "
            . "\"correct_answer\": \"B\", \"explanation\": \"...\"}";

        return self::callClaude($system, $user);
    }

    /* ── PRIVATE ──────────────────────────────────────────────────── */

    private static function buildScoringPrompt(string $type): string
    {
        $typeLabel = self::QUESTION_TYPES[$type]['label'] ?? $type;

        return <<<PROMPT
You are an expert SAT Reading & Writing scorer and tutor.
You are scoring a student's response to a {$typeLabel} question.

SCORING RUBRIC (1-4 scale):
4 = Excellent: Demonstrates thorough understanding, precise language
3 = Good: Mostly correct with minor issues
2 = Developing: Shows partial understanding, notable errors
1 = Beginning: Significant misunderstanding or major errors

Analyze the response and provide:
1. Overall score (1-4)
2. Specific skill scores for relevant sub-skills
3. Detailed feedback on what was done well
4. Specific areas for improvement with examples
5. Grammar/convention issues found (if applicable)
6. A corrected/improved version of their response
7. One actionable tip for improvement

Return ONLY valid JSON:
{
  "overall_score": 3,
  "skill_scores": {"skill_name": 3, ...},
  "strengths": ["...", "..."],
  "weak_areas": ["...", "..."],
  "feedback": "Detailed paragraph of feedback...",
  "grammar_issues": [{"original": "...", "corrected": "...", "rule": "..."}],
  "improved_version": "The improved response...",
  "tip": "One actionable tip..."
}
PROMPT;
    }

    private static function buildUserPrompt(string $passage, string $question, string $response, string $type): string
    {
        $prompt = "PASSAGE:\n{$passage}\n\nQUESTION:\n{$question}\n\n";
        $prompt .= "STUDENT'S RESPONSE:\n{$response}\n\n";
        $prompt .= "Score this response using the rubric. Return JSON only.";
        return $prompt;
    }

    private static function callClaude(string $system, string $user): ?array
    {
        $apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
        if (!$apiKey) return null;

        $model = defined('AI_TUTOR_MODEL') ? AI_TUTOR_MODEL : 'claude-sonnet-4-5-20250929';

        $payload = json_encode([
            'model'       => $model,
            'max_tokens'  => 2048,
            'temperature' => 0.3,
            'system'      => $system,
            'messages'    => [['role' => 'user', 'content' => $user]],
        ]);

        $ch = curl_init(self::ANTHROPIC_API);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return null;

        $response = json_decode($raw, true);
        $text = $response['content'][0]['text'] ?? '';

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) return $parsed;
        }

        return null;
    }

    private static function saveSubmission(int $userId, string $response, array $result, int $wordCount): void
    {
        $db = Database::connect();
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS rw_submissions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    prompt_id INT DEFAULT NULL,
                    response_text TEXT,
                    score_json TEXT,
                    overall_score TINYINT DEFAULT 0,
                    feedback_json TEXT,
                    word_count INT DEFAULT 0,
                    time_spent INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_date (created_at)
                )"
            );

            $stmt = $db->prepare(
                "INSERT INTO rw_submissions (user_id, response_text, score_json, overall_score, feedback_json, word_count)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $response,
                json_encode($result),
                $result['overall_score'] ?? 0,
                json_encode(['weak_areas' => $result['weak_areas'] ?? [], 'strengths' => $result['strengths'] ?? []]),
                $wordCount,
            ]);
        } catch (\Throwable $e) {
            error_log("AIEssayScorer::saveSubmission error: " . $e->getMessage());
        }
    }

    private static function calculateTrend(array $scores): string
    {
        if (count($scores) < 3) return 'insufficient_data';
        $recent = array_slice($scores, 0, 3);
        $older  = array_slice($scores, 3, 3);
        if (empty($older)) return 'insufficient_data';
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg  = array_sum($older)  / count($older);
        if ($recentAvg > $olderAvg + 0.3) return 'improving';
        if ($recentAvg < $olderAvg - 0.3) return 'declining';
        return 'stable';
    }
}
