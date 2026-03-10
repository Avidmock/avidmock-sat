<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AIQuestionGenerator
 *  Uses Claude to auto-generate SAT Math questions from topic specs.
 *  Supports bulk generation, difficulty targeting, and SAT-format
 *  compliance with automatic answer validation.
 * ═══════════════════════════════════════════════════════════════════
 */

class AIQuestionGenerator
{
    private const ANTHROPIC_API = 'https://api.anthropic.com/v1/messages';
    private const MAX_BATCH     = 10;

    /**
     * SAT Math domains + sub-skills for targeted generation.
     */
    public const DOMAINS = [
        'algebra' => [
            'label'  => 'Algebra',
            'skills' => [
                'linear-equations'       => 'Linear equations in one variable',
                'linear-inequalities'    => 'Linear inequalities',
                'systems-of-equations'   => 'Systems of two linear equations',
                'linear-functions'       => 'Linear functions and their graphs',
                'linear-word-problems'   => 'Linear equations word problems',
                'absolute-value'         => 'Absolute value equations & inequalities',
            ],
        ],
        'advanced-math' => [
            'label'  => 'Advanced Math',
            'skills' => [
                'quadratic-equations'    => 'Quadratic equations & factoring',
                'polynomial-functions'   => 'Polynomial functions & operations',
                'rational-expressions'   => 'Rational expressions & equations',
                'radical-exponents'      => 'Radicals and rational exponents',
                'exponential-functions'  => 'Exponential functions & growth/decay',
                'function-notation'      => 'Function notation & transformations',
                'nonlinear-systems'      => 'Nonlinear systems of equations',
            ],
        ],
        'problem-solving' => [
            'label'  => 'Problem Solving & Data Analysis',
            'skills' => [
                'ratios-rates'           => 'Ratios, rates, and proportions',
                'percentages'            => 'Percentages & percent change',
                'unit-conversions'       => 'Unit conversions',
                'scatterplots'           => 'Scatterplots & line of best fit',
                'two-way-tables'         => 'Two-way tables & relative frequency',
                'statistics'             => 'Mean, median, mode, range, std dev',
                'probability'            => 'Probability & conditional probability',
                'data-inference'         => 'Data inference & margin of error',
            ],
        ],
        'geometry-trig' => [
            'label'  => 'Geometry & Trigonometry',
            'skills' => [
                'area-volume'            => 'Area, volume, and surface area',
                'lines-angles'           => 'Lines, angles, and triangles',
                'right-triangles'        => 'Right triangles & Pythagorean theorem',
                'circles'                => 'Circles — equations, arcs, sectors',
                'trig-ratios'            => 'Trigonometric ratios (sin, cos, tan)',
                'coordinate-geometry'    => 'Coordinate geometry & distance',
            ],
        ],
    ];

    public const DIFFICULTY_LEVELS = ['easy', 'medium', 'hard'];

    /**
     * Generate a batch of SAT questions via Claude.
     *
     * @param  string $domain     Key from DOMAINS (e.g., 'algebra')
     * @param  string $skill      Key from DOMAINS[$domain]['skills']
     * @param  string $difficulty  easy|medium|hard
     * @param  int    $count      1–10
     * @param  array  $constraints Optional: ['avoid_topics' => [...], 'style' => 'word_problem', etc.]
     * @return array  ['questions' => [...], 'meta' => [...]]
     */
    public static function generate(
        string $domain,
        string $skill,
        string $difficulty = 'medium',
        int    $count = 5,
        array  $constraints = []
    ): array {
        $count = min(max($count, 1), self::MAX_BATCH);

        $domainLabel = self::DOMAINS[$domain]['label'] ?? ucfirst($domain);
        $skillLabel  = self::DOMAINS[$domain]['skills'][$skill] ?? $skill;

        $systemPrompt = self::buildSystemPrompt();
        $userPrompt   = self::buildUserPrompt($domainLabel, $skillLabel, $difficulty, $count, $constraints);

        $response = self::callClaude($systemPrompt, $userPrompt);

        if (!$response || empty($response['questions'])) {
            return ['questions' => [], 'meta' => ['error' => 'Generation failed', 'raw' => $response]];
        }

        // Validate each question
        $validated = [];
        foreach ($response['questions'] as $q) {
            $v = self::validateQuestion($q);
            if ($v['valid']) {
                $q['domain']     = $domain;
                $q['skill']      = $skill;
                $q['difficulty'] = $difficulty;
                $q['generated']  = true;
                $validated[]     = $q;
            }
        }

        return [
            'questions' => $validated,
            'meta'      => [
                'requested'  => $count,
                'generated'  => count($response['questions']),
                'validated'  => count($validated),
                'domain'     => $domainLabel,
                'skill'      => $skillLabel,
                'difficulty' => $difficulty,
            ],
        ];
    }

    /**
     * Save generated questions to a quiz.
     */
    public static function saveToQuiz(int $quizId, array $questions): array
    {
        $db = Database::connect();
        $saved = 0;

        // Get current max position
        $maxPos = (int) $db->query(
            "SELECT COALESCE(MAX(position), 0) FROM sat_quiz_questions WHERE quiz_id = {$quizId}"
        )->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO sat_quiz_questions
             (quiz_id, position, type, stem, option_a, option_b, option_c, option_d,
              correct_answer, explanation, difficulty, points)
             VALUES
             (:quiz_id, :position, :type, :stem, :oa, :ob, :oc, :od,
              :correct, :explanation, :difficulty, :points)"
        );

        foreach ($questions as $q) {
            $maxPos++;
            try {
                $stmt->execute([
                    ':quiz_id'    => $quizId,
                    ':position'   => $maxPos,
                    ':type'       => $q['type'] ?? 'multiple_choice',
                    ':stem'       => $q['stem'],
                    ':oa'         => $q['options']['A'] ?? '',
                    ':ob'         => $q['options']['B'] ?? '',
                    ':oc'         => $q['options']['C'] ?? '',
                    ':od'         => $q['options']['D'] ?? '',
                    ':correct'    => strtoupper($q['correct_answer']),
                    ':explanation'=> $q['explanation'] ?? '',
                    ':difficulty' => $q['difficulty'] ?? 'medium',
                    ':points'     => $q['difficulty'] === 'hard' ? 3 : ($q['difficulty'] === 'medium' ? 2 : 1),
                ]);
                $saved++;
            } catch (\Throwable $e) {
                error_log("AIQuestionGenerator::saveToQuiz error: " . $e->getMessage());
            }
        }

        return ['saved' => $saved, 'total' => count($questions)];
    }

    /**
     * Generate questions and immediately save to a quiz.
     */
    public static function generateAndSave(
        int    $quizId,
        string $domain,
        string $skill,
        string $difficulty = 'medium',
        int    $count = 5
    ): array {
        $result = self::generate($domain, $skill, $difficulty, $count);
        if (empty($result['questions'])) {
            return ['saved' => 0, 'meta' => $result['meta']];
        }
        $saveResult = self::saveToQuiz($quizId, $result['questions']);
        return array_merge($saveResult, ['meta' => $result['meta']]);
    }

    /* ── PRIVATE ──────────────────────────────────────────────────── */

    private static function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert SAT Math question writer for the College Board Digital SAT.
You create questions that are indistinguishable from real SAT questions.

RULES:
1. Every question MUST be in official SAT format: a stem (question text) with exactly 4 answer choices (A, B, C, D).
2. Only ONE answer choice is correct. The other 3 must be plausible distractors based on common student mistakes.
3. Explanations must be step-by-step, showing the complete solution process.
4. Use LaTeX notation for math: wrap inline math in \( ... \) and display math in \[ ... \]
5. Difficulty levels:
   - Easy: 1-2 step problems, basic concept application
   - Medium: 2-3 steps, requires connecting concepts or careful reading
   - Hard: 3+ steps, requires creative problem-solving, multiple concepts, or tricky wording
6. Avoid cultural bias, obscure references, or unnecessarily complex language.
7. Grid-in questions should have a single numerical answer (no choices).

RESPOND ONLY with valid JSON. No markdown fences, no commentary.
PROMPT;
    }

    private static function buildUserPrompt(
        string $domain,
        string $skill,
        string $difficulty,
        int    $count,
        array  $constraints
    ): string {
        $prompt = "Generate exactly {$count} SAT Math question(s).\n\n";
        $prompt .= "Domain: {$domain}\n";
        $prompt .= "Specific Skill: {$skill}\n";
        $prompt .= "Difficulty: {$difficulty}\n\n";

        if (!empty($constraints['style'])) {
            $prompt .= "Style preference: {$constraints['style']}\n";
        }
        if (!empty($constraints['avoid_topics'])) {
            $prompt .= "Avoid these specific topics: " . implode(', ', $constraints['avoid_topics']) . "\n";
        }
        if (!empty($constraints['context'])) {
            $prompt .= "Context: {$constraints['context']}\n";
        }

        $prompt .= <<<'FORMAT'

Return a JSON object with this exact structure:
{
  "questions": [
    {
      "type": "multiple_choice",
      "stem": "Question text here with \\( math \\) notation",
      "options": {
        "A": "First option",
        "B": "Second option",
        "C": "Third option",
        "D": "Fourth option"
      },
      "correct_answer": "B",
      "explanation": "Step-by-step solution:\n1. First step...\n2. Second step...\nTherefore the answer is B.",
      "sat_strategy_tip": "One sentence test-taking tip for this type of question."
    }
  ]
}
FORMAT;

        return $prompt;
    }

    private static function callClaude(string $system, string $user): ?array
    {
        $apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
        if (!$apiKey) {
            error_log('AIQuestionGenerator: ANTHROPIC_API_KEY not set');
            return null;
        }

        $model = defined('ANTHROPIC_MODEL') ? ANTHROPIC_MODEL : 'claude-sonnet-4-5-20250929';

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 4096,
            'temperature'=> 0.8,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $ch = curl_init(self::ANTHROPIC_API);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("AIQuestionGenerator: Claude API returned HTTP {$httpCode}: {$raw}");
            return null;
        }

        $response = json_decode($raw, true);
        $text = $response['content'][0]['text'] ?? '';

        // Extract JSON from response (handle potential markdown fences)
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        error_log("AIQuestionGenerator: Failed to parse Claude response");
        return null;
    }

    /**
     * Validate a generated question has all required fields.
     */
    private static function validateQuestion(array $q): array
    {
        $errors = [];

        if (empty($q['stem']) || strlen($q['stem']) < 10) {
            $errors[] = 'Stem too short or missing';
        }

        if ($q['type'] === 'multiple_choice') {
            foreach (['A', 'B', 'C', 'D'] as $opt) {
                if (empty($q['options'][$opt])) {
                    $errors[] = "Option {$opt} missing";
                }
            }
            if (empty($q['correct_answer']) || !in_array(strtoupper($q['correct_answer']), ['A','B','C','D'])) {
                $errors[] = 'Invalid correct_answer';
            }
        }

        if (empty($q['explanation'])) {
            $errors[] = 'Explanation missing';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Get generation stats for admin dashboard.
     */
    public static function getStats(): array
    {
        $db = Database::connect();
        try {
            // Count AI-generated questions (those with points matching the pattern)
            $totalGenerated = (int) $db->query(
                "SELECT COUNT(*) FROM sat_quiz_questions WHERE difficulty IN ('easy','medium','hard')"
            )->fetchColumn();

            $byDifficulty = $db->query(
                "SELECT difficulty, COUNT(*) as cnt
                 FROM sat_quiz_questions
                 WHERE difficulty IN ('easy','medium','hard')
                 GROUP BY difficulty"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);

            return [
                'total'         => $totalGenerated,
                'by_difficulty'  => $byDifficulty,
            ];
        } catch (\Throwable) {
            return ['total' => 0, 'by_difficulty' => []];
        }
    }
}
