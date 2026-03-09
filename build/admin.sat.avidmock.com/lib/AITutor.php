<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AITutor
 *  Claude-powered Socratic tutor with context injection & streaming.
 * ═══════════════════════════════════════════════════════════════════
 */

class AITutor
{
    /**
     * Send a message and stream the response via SSE.
     */
    public static function ask(int $userId, string $message, ?string $subject = null, ?int $questionId = null): void
    {
        // Rate limit check
        if (!RateLimit::check("ai_tutor:{$userId}", self::getDailyLimit($userId))) {
            self::streamError('Daily AI tutor limit reached. Upgrade to Pro for unlimited access.');
            return;
        }

        // Build context-aware system prompt
        $systemPrompt = self::buildSystemPrompt($userId, $subject, $questionId);

        // Get conversation history (last 10 messages)
        $history = self::getRecentHistory($userId, 10);

        // Build messages array
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Save user message
        self::saveMessage($userId, 'user', $message, $subject);

        // Stream from Claude API
        $assistantResponse = self::streamFromClaude($systemPrompt, $messages);

        // Save assistant response
        if ($assistantResponse) {
            self::saveMessage($userId, 'assistant', $assistantResponse, $subject);
            RateLimit::increment("ai_tutor:{$userId}");

            // Award XP for tutor session (once per 5 messages)
            $todayCount = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM ai_tutor_messages
                 WHERE user_id = ? AND role = 'user' AND DATE(created_at) = CURDATE()",
                [$userId]
            );
            if ($todayCount > 0 && $todayCount % 5 === 0) {
                XPSystem::award($userId, XP_AI_TUTOR_SESSION, 'ai_tutor_session');
            }
        }
    }

    /**
     * Build context-injected system prompt.
     */
    private static function buildSystemPrompt(int $userId, ?string $subject, ?int $questionId): string
    {
        // Load base prompt
        $promptFile = __DIR__ . '/../config/ai-prompts/tutor-socratic.txt';
        $basePrompt = file_exists($promptFile) ? file_get_contents($promptFile) : 'You are a helpful SAT tutor.';

        // Inject student context
        $user = User::findById($userId);
        $streak = StudyStreak::get($userId);
        $daysLeft = Auth::daysUntilTest();

        $context = "\n\n<student_context>\n";
        $context .= "Name: " . ($user['name'] ?? 'Student') . "\n";
        $context .= "Target Score: " . ($user['target_score'] ?? 'Not set') . "\n";
        $context .= "Test Date: " . ($user['test_date'] ?? 'Not set');
        if ($daysLeft !== null) $context .= " ({$daysLeft} days away)";
        $context .= "\n";
        $context .= "Streak: " . ($streak['current_streak'] ?? 0) . " days\n";
        $context .= "Subscription: " . current_tier() . "\n";

        // Add subject-specific accuracy
        if ($subject) {
            $catPerf = Database::fetch(
                "SELECT avg_score, mastery_level FROM category_performance WHERE user_id = ? AND category LIKE ?",
                [$userId, "%{$subject}%"]
            );
            if ($catPerf) {
                $context .= "Current Accuracy ({$subject}): {$catPerf['avg_score']}%\n";
                $context .= "Mastery Level: {$catPerf['mastery_level']}\n";
            }
        }
        $context .= "</student_context>\n";

        // Add question context if asking about a specific question
        if ($questionId) {
            $q = Question::getById($questionId);
            if ($q) {
                $context .= "\n<question_context>\n";
                $context .= "Question Stem: {$q['stem']}\n";
                $context .= "Choices: A) {$q['choice_a']}  B) {$q['choice_b']}  C) {$q['choice_c']}  D) {$q['choice_d']}\n";
                $context .= "Correct Answer: {$q['correct_answer']}\n";

                // Check if student answered it
                $answer = Database::fetch(
                    "SELECT user_answer, is_correct FROM question_answers
                     WHERE user_id = ? AND question_id = ? ORDER BY created_at DESC LIMIT 1",
                    [$userId, $questionId]
                );
                if ($answer) {
                    $context .= "Student's Answer: {$answer['user_answer']}\n";
                    $context .= "Was Correct: " . ($answer['is_correct'] ? 'Yes' : 'No') . "\n";
                }

                $context .= "Explanation: {$q['explanation']}\n";
                $context .= "Topic: {$q['difficulty']}\n";
                $context .= "</question_context>\n";
            }
        }

        return $basePrompt . $context;
    }

    /**
     * Stream response from Claude API via SSE.
     */
    private static function streamFromClaude(string $system, array $messages): ?string
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $payload = json_encode([
            'model'      => AI_TUTOR_MODEL,
            'max_tokens' => AI_MAX_TOKENS,
            'temperature'=> AI_TEMPERATURE,
            'system'     => $system,
            'messages'   => $messages,
            'stream'     => true,
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                echo $data;
                if (ob_get_level()) ob_flush();
                flush();
                return strlen($data);
            },
        ]);

        $fullResponse = '';

        // Capture the full response for saving
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullResponse) {
            // Parse SSE chunks to extract text
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'data: ')) {
                    $json = json_decode(substr($line, 6), true);
                    if (isset($json['delta']['text'])) {
                        $fullResponse .= $json['delta']['text'];
                    }
                }
            }

            // Forward to client
            echo $data;
            if (ob_get_level()) ob_flush();
            flush();
            return strlen($data);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "data: [DONE]\n\n";
        if (ob_get_level()) ob_flush();
        flush();

        return $httpCode === 200 ? $fullResponse : null;
    }

    private static function streamError(string $message): void
    {
        header('Content-Type: text/event-stream');
        echo "data: " . json_encode(['type' => 'error', 'message' => $message]) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    }

    /**
     * Save a message to history.
     */
    public static function saveMessage(int $userId, string $role, string $content, ?string $subject = null): int
    {
        return Database::insert('ai_tutor_messages', [
            'user_id'         => $userId,
            'role'            => $role,
            'content'         => $content,
            'subject_context' => $subject,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getRecentHistory(int $userId, int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT role, content, created_at FROM ai_tutor_messages
             WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function getFullHistory(int $userId, int $page = 1): array
    {
        return Database::paginate(
            "SELECT * FROM ai_tutor_messages WHERE user_id = ? ORDER BY created_at DESC",
            [$userId], $page, 50
        );
    }

    private static function getDailyLimit(int $userId): int
    {
        $tier = current_tier();
        return $tier === TIER_FREE ? AI_TUTOR_FREE_LIMIT : AI_TUTOR_PRO_LIMIT;
    }
}