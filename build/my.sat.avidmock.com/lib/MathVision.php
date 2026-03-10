<?php
/**
 * Avidmock SAT Math Scanner — MathVision Processing Class
 *
 * Handles AI-powered math problem analysis via Claude API,
 * SAT domain mapping, quiz lookup, and scan logging.
 */

declare(strict_types=1);

class MathVision
{
    private $db;
    private string $claudeApiKey;
    private string $claudeApiUrl = 'https://api.anthropic.com/v1/messages';
    private string $claudeModel = 'claude-sonnet-4-5-20250514';

    /**
     * SAT Math domains and sub-skills for mapping.
     */
    private const SAT_DOMAINS = [
        'algebra' => [
            'label' => 'Algebra',
            'skills' => [
                'linear-equations' => 'Linear equations in one variable',
                'linear-equations-two' => 'Linear equations in two variables',
                'linear-functions' => 'Linear functions',
                'systems-linear' => 'Systems of two linear equations in two variables',
                'linear-inequalities' => 'Linear inequalities in one or two variables',
            ],
        ],
        'advanced-math' => [
            'label' => 'Advanced Math',
            'skills' => [
                'equivalent-expressions' => 'Equivalent expressions',
                'nonlinear-equations' => 'Nonlinear equations in one variable and systems of equations',
                'nonlinear-functions' => 'Nonlinear functions',
                'quadratic-equations' => 'Quadratic equations',
                'polynomial-functions' => 'Polynomial and rational functions',
                'exponential-functions' => 'Exponential and radical functions',
            ],
        ],
        'problem-solving' => [
            'label' => 'Problem-Solving and Data Analysis',
            'skills' => [
                'ratios-rates' => 'Ratios, rates, proportional relationships, and units',
                'percentages' => 'Percentages',
                'one-variable-data' => 'One-variable data: distributions and measures of center and spread',
                'two-variable-data' => 'Two-variable data: models and scatterplots',
                'probability' => 'Probability and conditional probability',
                'inference' => 'Inference from sample statistics and margin of error',
                'evaluating-claims' => 'Evaluating statistical claims: observational studies and experiments',
            ],
        ],
        'geometry' => [
            'label' => 'Geometry and Trigonometry',
            'skills' => [
                'area-volume' => 'Area and volume',
                'lines-angles' => 'Lines, angles, and triangles',
                'right-triangles' => 'Right triangles and trigonometry',
                'circles' => 'Circles',
            ],
        ],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->claudeApiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : ($_ENV['CLAUDE_API_KEY'] ?? '');

        if (empty($this->claudeApiKey)) {
            throw new \RuntimeException('Claude API key not configured.');
        }
    }

    /**
     * Analyze a math problem from an image using Claude Vision.
     *
     * @param string $base64Image Base64-encoded image data (without data URL prefix).
     * @return array Parsed analysis result.
     */
    public function analyzeImage(string $base64Image): array
    {
        // Detect media type from the image header bytes
        $mediaType = $this->detectImageMediaType($base64Image);

        $systemPrompt = $this->buildSystemPrompt();

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mediaType,
                            'data' => $base64Image,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->buildUserPrompt(),
                    ],
                ],
            ],
        ];

        $response = $this->callClaudeAPI($systemPrompt, $messages);
        return $this->parseAnalysis($response);
    }

    /**
     * Analyze a math problem from text using Claude Messages API.
     *
     * @param string $text The math problem text.
     * @return array Parsed analysis result.
     */
    public function analyzeText(string $text): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        $messages = [
            [
                'role' => 'user',
                'content' => $this->buildUserPrompt($text),
            ],
        ];

        $response = $this->callClaudeAPI($systemPrompt, $messages);
        return $this->parseAnalysis($response);
    }

    /**
     * Find quizzes related to the identified SAT domain and skill.
     *
     * @param string $domainKey The domain key (e.g., "algebra").
     * @param string $skillKey  The skill key (e.g., "linear-equations").
     * @return array List of similar quiz objects.
     */
    public function findSimilarQuizzes(string $domainKey, string $skillKey): array
    {
        try {
            $query = '
                SELECT q.id, q.title, q.difficulty, q.question_count
                FROM quizzes q
                WHERE q.domain_key = :domain
                AND q.skill_key = :skill
                AND q.is_active = 1
                ORDER BY q.sort_order ASC, q.created_at DESC
                LIMIT 3
            ';

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'domain' => $domainKey,
                'skill' => $skillKey,
            ]);

            $quizzes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // If no exact skill match, try domain-level
            if (empty($quizzes)) {
                $query = '
                    SELECT q.id, q.title, q.difficulty, q.question_count
                    FROM quizzes q
                    WHERE q.domain_key = :domain
                    AND q.is_active = 1
                    ORDER BY q.sort_order ASC, q.created_at DESC
                    LIMIT 3
                ';
                $stmt = $this->db->prepare($query);
                $stmt->execute(['domain' => $domainKey]);
                $quizzes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            return array_map(function ($quiz) {
                return [
                    'id' => (int) $quiz['id'],
                    'title' => $quiz['title'],
                    'difficulty' => $quiz['difficulty'] ?? 'medium',
                    'question_count' => (int) ($quiz['question_count'] ?? 10),
                ];
            }, $quizzes);
        } catch (\Exception $e) {
            error_log('MathVision::findSimilarQuizzes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Log a scan to the math_scans table.
     *
     * @param int    $userId The user ID.
     * @param string $type   "image" or "text".
     * @param array  $result The analysis result.
     * @param string $source The source ("extension", "notebook", "web").
     */
    public function logScan(int $userId, string $type, array $result, string $source = 'web'): void
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO math_scans (
                    user_id, scan_type, source, problem_text,
                    answer, domain_key, skill_key, difficulty,
                    full_result, created_at
                ) VALUES (
                    :user_id, :scan_type, :source, :problem_text,
                    :answer, :domain_key, :skill_key, :difficulty,
                    :full_result, NOW()
                )
            ');

            $stmt->execute([
                'user_id' => $userId,
                'scan_type' => $type,
                'source' => $source,
                'problem_text' => mb_substr($result['problem'] ?? '', 0, 2000),
                'answer' => mb_substr($result['answer'] ?? '', 0, 500),
                'domain_key' => $result['sat_mapping']['domain_key'] ?? 'algebra',
                'skill_key' => $result['sat_mapping']['skill_key'] ?? 'general',
                'difficulty' => $result['difficulty'] ?? 'medium',
                'full_result' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            error_log('MathVision::logScan error: ' . $e->getMessage());
        }
    }

    /**
     * Update the user's daily streak.
     *
     * @param int $userId The user ID.
     */
    public function updateStreak(int $userId): void
    {
        try {
            $today = date('Y-m-d');

            // Check if the user already has activity today
            $stmt = $this->db->prepare(
                'SELECT last_active_date, streak FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) return;

            $lastActive = $user['last_active_date'] ?? null;
            $currentStreak = (int) ($user['streak'] ?? 0);

            if ($lastActive === $today) {
                // Already counted today
                return;
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if ($lastActive === $yesterday) {
                // Consecutive day — increment streak
                $newStreak = $currentStreak + 1;
            } else {
                // Streak broken — reset to 1
                $newStreak = 1;
            }

            $stmt = $this->db->prepare(
                'UPDATE users SET streak = ?, last_active_date = ? WHERE id = ?'
            );
            $stmt->execute([$newStreak, $today, $userId]);
        } catch (\Exception $e) {
            error_log('MathVision::updateStreak error: ' . $e->getMessage());
        }
    }

    // ---- Private Methods ----

    /**
     * Build the system prompt for Claude.
     */
    private function buildSystemPrompt(): string
    {
        $domainList = '';
        foreach (self::SAT_DOMAINS as $key => $domain) {
            $domainList .= "\n{$domain['label']} (key: {$key}):";
            foreach ($domain['skills'] as $skillKey => $skillLabel) {
                $domainList .= "\n  - {$skillLabel} (key: {$skillKey})";
            }
        }

        return <<<PROMPT
You are an expert SAT Math tutor and problem solver for Avidmock, a premium SAT preparation platform.

Your job:
1. Identify the math problem presented (from image or text).
2. Solve it completely and correctly, showing clear step-by-step work.
3. Map it to the correct SAT Math domain and sub-skill.
4. Rate its difficulty relative to real SAT exams.
5. Provide a strategic tip for solving similar problems on the SAT.

SAT Math Domains and Skills:
{$domainList}

IMPORTANT RULES:
- Always verify your answer by checking it against the original problem.
- Show every logical step clearly. Students are learning — do not skip steps.
- Use LaTeX notation for mathematical expressions in the steps_latex field.
- For the "steps" field, use plain text that reads naturally.
- Map to the most specific sub-skill possible.
- Difficulty should reflect how hard this would be on a real SAT (easy/medium/hard).
- Strategy tips should be practical and specific to the problem type.

Respond ONLY with valid JSON in exactly this format:
{
  "problem": "The recognized problem statement in clean text",
  "answer": "The final answer (e.g., x = 5, or 42, or y = 2x + 3)",
  "steps": [
    "Step 1: Description in plain English with math",
    "Step 2: ...",
    "Step 3: ..."
  ],
  "steps_latex": [
    "\\\\text{Step 1: } ...",
    "\\\\text{Step 2: } ...",
    "\\\\text{Step 3: } ..."
  ],
  "sat_mapping": {
    "domain": "Domain Label",
    "skill": "Skill Label",
    "domain_key": "domain-key",
    "skill_key": "skill-key"
  },
  "difficulty": "easy|medium|hard",
  "strategy_tip": "A helpful strategy tip for this type of problem on the SAT."
}
PROMPT;
    }

    /**
     * Build the user prompt.
     *
     * @param string|null $text Optional problem text (for text-only requests).
     */
    private function buildUserPrompt(?string $text = null): string
    {
        if ($text) {
            return "Solve this math problem and provide your analysis in the JSON format specified:\n\n{$text}";
        }

        return 'Look at this image of a math problem. Identify the problem, solve it step-by-step, and provide your analysis in the JSON format specified. If the image contains multiple problems, solve the most prominent one.';
    }

    /**
     * Call the Claude API.
     *
     * @param string $systemPrompt The system prompt.
     * @param array  $messages     The messages array.
     * @return string The raw text response from Claude.
     */
    private function callClaudeAPI(string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $this->claudeModel,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $ch = curl_init($this->claudeApiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->claudeApiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("Claude API connection error: {$curlError}");
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Claude API error: {$errorMsg}");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['content'][0]['text'])) {
            throw new \RuntimeException('Unexpected Claude API response format.');
        }

        return $data['content'][0]['text'];
    }

    /**
     * Parse the Claude response into a structured analysis array.
     *
     * @param string $rawResponse The raw text response from Claude.
     * @return array Structured analysis.
     */
    private function parseAnalysis(string $rawResponse): array
    {
        // Extract JSON from the response (Claude may wrap it in markdown code blocks)
        $json = $rawResponse;

        // Remove markdown code fences if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $matches)) {
            $json = $matches[1];
        }

        $parsed = json_decode(trim($json), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Attempt to extract JSON object from the text
            if (preg_match('/\{[\s\S]*\}/', $rawResponse, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }

        if (!$parsed || !is_array($parsed)) {
            throw new \RuntimeException('Failed to parse AI analysis response.');
        }

        // Validate and normalize the mapping
        $satMapping = $parsed['sat_mapping'] ?? [];
        $domainKey = $satMapping['domain_key'] ?? 'algebra';
        $skillKey = $satMapping['skill_key'] ?? 'general';

        // Validate domain key exists
        if (!isset(self::SAT_DOMAINS[$domainKey])) {
            // Try to fuzzy match
            $domainKey = $this->fuzzyMatchDomain($satMapping['domain'] ?? '');
        }

        // Validate skill key exists under domain
        if (isset(self::SAT_DOMAINS[$domainKey]) && !isset(self::SAT_DOMAINS[$domainKey]['skills'][$skillKey])) {
            $skillKey = $this->fuzzyMatchSkill($domainKey, $satMapping['skill'] ?? '');
        }

        $parsed['sat_mapping'] = [
            'domain' => self::SAT_DOMAINS[$domainKey]['label'] ?? 'Algebra',
            'skill' => self::SAT_DOMAINS[$domainKey]['skills'][$skillKey] ?? 'General',
            'domain_key' => $domainKey,
            'skill_key' => $skillKey,
        ];

        // Normalize difficulty
        $difficulty = strtolower($parsed['difficulty'] ?? 'medium');
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'medium';
        }
        $parsed['difficulty'] = $difficulty;

        // Ensure steps are arrays
        if (!is_array($parsed['steps'] ?? null)) {
            $parsed['steps'] = [$parsed['steps'] ?? 'Solution provided.'];
        }
        if (!is_array($parsed['steps_latex'] ?? null)) {
            $parsed['steps_latex'] = $parsed['steps'];
        }

        return $parsed;
    }

    /**
     * Detect image media type from base64 data.
     */
    private function detectImageMediaType(string $base64): string
    {
        $header = base64_decode(substr($base64, 0, 16));

        if (str_starts_with($header, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($header, 'GIF')) {
            return 'image/gif';
        }
        if (str_starts_with($header, 'RIFF') && strpos($header, 'WEBP') !== false) {
            return 'image/webp';
        }

        // Default to PNG (most likely from screenshot)
        return 'image/png';
    }

    /**
     * Fuzzy match a domain label to a domain key.
     */
    private function fuzzyMatchDomain(string $label): string
    {
        $label = strtolower($label);

        foreach (self::SAT_DOMAINS as $key => $domain) {
            if (str_contains(strtolower($domain['label']), $label) || str_contains($label, strtolower($domain['label']))) {
                return $key;
            }
        }

        // Keyword fallback
        if (str_contains($label, 'algebra')) return 'algebra';
        if (str_contains($label, 'advanced') || str_contains($label, 'quadratic') || str_contains($label, 'polynomial')) return 'advanced-math';
        if (str_contains($label, 'geometry') || str_contains($label, 'trig')) return 'geometry';
        if (str_contains($label, 'data') || str_contains($label, 'probability') || str_contains($label, 'statistic')) return 'problem-solving';

        return 'algebra';
    }

    /**
     * Fuzzy match a skill label to a skill key within a domain.
     */
    private function fuzzyMatchSkill(string $domainKey, string $label): string
    {
        if (!isset(self::SAT_DOMAINS[$domainKey])) {
            return 'general';
        }

        $label = strtolower($label);
        $skills = self::SAT_DOMAINS[$domainKey]['skills'];

        foreach ($skills as $key => $skillLabel) {
            if (str_contains(strtolower($skillLabel), $label) || str_contains($label, strtolower($skillLabel))) {
                return $key;
            }
        }

        // Return the first skill as fallback
        return array_key_first($skills) ?? 'general';
    }
}
