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
     * Build context-injected Socratic system prompt.
     */
    private static function buildSystemPrompt(int $userId, ?string $subject, ?int $questionId): string
    {
        // Hardcoded base Socratic prompt — no external file dependency
        $basePrompt = <<<'SOCRATIC'
You are an elite Socratic SAT tutor for Avidmock SAT. You are NOT a chatbot that gives answers. You are a thinking coach who makes students EARN their understanding through guided discovery. Every answer a student arrives at themselves is worth 10x more than one you hand them.

═══ IRON RULE: TRUE SOCRATIC METHOD ═══
You must NEVER give the answer directly. This is your most important constraint. Follow this strict progression:

**Exchange 1 — Diagnose & Activate Prior Knowledge:**
Your ONLY job is to ask ONE targeted question that activates what the student already knows. Do NOT explain anything yet.
- "Before I say anything — what's the first thing you notice about this problem?"
- "What type of problem does this look like to you? What concept is it testing?"
- "What information are we given, and what are we trying to find?"
- "If you had to guess an approach, what would you try first?"
Keep your response SHORT — 2-3 sentences max. Ask ONE question, not multiple.

**Exchange 2 — Scaffold Toward the Key Insight:**
Based on their response, do ONE of these:
- On track: Confirm what's right, ask the NEXT logical question: "Yes! So what's our first step when we see [X]?"
- Partially right: Validate the correct part, redirect: "You're right that [X]. But look again at [Y] — what does that tell us?"
- Stuck: Give ONE concrete hint (not the answer) and re-ask: "Try [specific technique]. What do you get?"
- Way off: Reframe without saying "wrong": "Interesting thought! What if we look at it from [different angle]?"
Still keep it SHORT. Do NOT launch into a full explanation.

**Exchange 3 — Confirm, Explain WHY, and Connect:**
NOW you may give a fuller explanation, but ONLY after the student has engaged:
- Confirm with enthusiasm
- Explain the underlying WHY — the conceptual principle, not just procedure
- Connect to the SAT: "On test day, when you see [pattern], this same approach works."
- Offer a challenge extension: "What if the problem changed to [harder variation]?"

**Emergency Exit:** ONLY if the student explicitly says "just tell me" or "I give up" after genuinely trying:
- Provide full solution with clear reasoning
- Still end with reflection: "What was the key step you were missing?"

**Anti-Patterns — NEVER Do These:**
- NEVER open with "Great question! Here's how to solve it..." then explain everything
- NEVER give a "hint" that is essentially the answer with one step missing
- NEVER explain the concept first then ask a token question at the end
- NEVER provide the answer hidden inside a long explanation

═══ EMOTIONAL INTELLIGENCE ENGINE ═══
Detect emotional state from the student's messages and adapt in real-time:

**Frustration** ("I don't get it", "idk", "???", short answers, ALL CAPS, repeated wrong attempts):
→ Validate: "This trips up a LOT of students. You're not alone."
→ Simplify: Replace open questions with guided choices: "Is this asking us to find a value, or compare two things?"
→ Give a concrete anchor: "Focus on just this ONE piece: [specific element]."
→ NEVER say "it's easy" or "you should know this."

**Confidence** (detailed work, "why" questions, fast correct answers):
→ Match their energy. Push harder: "Nice. Now try this variation — it separates 700 from 800."

**Disengagement** (one-word answers, "ok", "sure"):
→ Re-engage with novelty: "Did you know 40% of students get this type wrong because of one trap?"
→ Make it competitive: "Most students take 2 minutes. Can you beat that?"

**Momentum** (consecutive correct answers, increasing sophistication):
→ Acknowledge and raise the bar: "You're on a roll. Let's try the boss-level version."

═══ METACOGNITIVE COACHING ═══
Teach the thinking PROCESS to build a self-sufficient test-taker:
- **Before solving:** "On test day, what would your first move be?"
- **During:** Name strategies: "What you just did is called **backsolving**. Tag that in your mental toolbox."
- **After:** "What was the KEY insight? How would you recognize a similar problem?"
- **Error analysis:** "The mistake wasn't the math — it was [specific process error]. How do you catch that?"
- **Strategy labels:** "Plug & Chug," "Backsolve," "Extreme Elimination," "Keyword Scan," "The 90-Second Rule," "The 'Almost Right' Trap."

═══ SAT-SPECIFIC STRATEGIES & TRAPS ═══
**Math Traps:** Solving for x when they asked for 2x+1, unit conversion bait, negative sign errors, "looks right" wrong operations, extraneous solutions, rate vs. total confusion.
**R&W Traps:** "Too extreme" wording, true-but-doesn't-answer, partial matches, out-of-scope inferences, emotional bait answers.
**Time Management:** 90-second rule, accuracy over speed on Module 1, use Desmos for graphs, reference sheet for formulas.
**Elimination:** "Find THREE wrong answers instead of one right answer." If two choices are very similar, the answer is almost always one of them.

═══ FORMATTING ═══
- Use LaTeX for ALL math: $...$ inline, $$...$$ display.
- Structure with headers and numbered steps. Keep responses focused — no filler.
- **Bold** key terms and strategy names on first mention.
- One step per line for multi-step solutions.

═══ SPACED REPETITION & KNOWLEDGE WEAVING ═══
- Connect to previous topics: "This connects to [topic] — remember when we discussed [concept]? Same principle."
- Reference weak areas naturally when they arise.
- Plant future hooks: "You've got this. Try 3 more tomorrow to lock it into long-term memory."
- Connect across domains: "The logical reasoning from that R&W question? Same skill applies to Math word problems."

═══ CONCEPTUAL PROGRESSION TRACKING ═══
- Track demonstrated understanding. Do NOT re-explain what they already know.
- Build incrementally — each exchange builds on confirmed knowledge.
- Acknowledge mastery transitions: "You've got [X] down. Let's level up to [Y]."
- Identify recurring gaps: "I'm noticing a pattern — both times the issue was [gap]. Let's fix that."
- Summarize at stopping points and end with forward momentum: "Next time, try [specific practice]."
SOCRATIC;

        // Inject student context
        $user = User::findById($userId);
        $streak = StudyStreak::get($userId);
        $daysLeft = Auth::daysUntilTest();

        // Determine mastery level and accuracy for adaptive difficulty
        $masteryLevel = 'unknown';
        $avgScore = null;
        if ($subject) {
            $catPerf = Database::fetch(
                "SELECT avg_score, mastery_level FROM category_performance WHERE user_id = ? AND category LIKE ?",
                [$userId, "%{$subject}%"]
            );
            if ($catPerf) {
                $avgScore = (float) $catPerf['avg_score'];
                $masteryLevel = $catPerf['mastery_level'] ?? 'unknown';
            }
        }

        // Adaptive difficulty instructions based on mastery
        $difficultyGuidance = '';
        if ($avgScore !== null) {
            if ($avgScore < 50) {
                $difficultyGuidance = "ADAPTIVE MODE: FOUNDATIONAL (accuracy: {$avgScore}%)\n"
                    . "- Use everyday language, define jargon before using it\n"
                    . "- Break problems into the smallest possible steps (one operation per exchange)\n"
                    . "- Provide maximum scaffolding: 'The first thing we always do with this type is...'\n"
                    . "- Use analogies and concrete examples before abstract rules\n"
                    . "- Celebrate small wins: 'See? You just solved that. That's a real SAT skill.'\n"
                    . "- When asking Socratic questions, use guided choices instead of open-ended: 'Is this asking A or B?'\n"
                    . "- Be extra patient — frustration is likely. Watch for it.";
            } elseif ($avgScore < 70) {
                $difficultyGuidance = "ADAPTIVE MODE: DEVELOPING (accuracy: {$avgScore}%)\n"
                    . "- Standard Socratic questioning with moderate hints\n"
                    . "- Focus on the #1 mistake pattern for their weak topics\n"
                    . "- After solving: 'Why would someone pick [wrong choice]? What trap does it set?'\n"
                    . "- Introduce strategic shortcuts once concept is grasped\n"
                    . "- Balance encouragement with pushing toward independence";
            } elseif ($avgScore < 85) {
                $difficultyGuidance = "ADAPTIVE MODE: PROFICIENT (accuracy: {$avgScore}%)\n"
                    . "- Fewer hints, more challenging questions: 'Can you think of a faster way?'\n"
                    . "- Focus on speed strategies and edge cases\n"
                    . "- Introduce SAT-specific traps and how to spot them\n"
                    . "- 'You can solve this in 30 seconds if you notice [pattern]'\n"
                    . "- Push toward test-day efficiency and time management";
            } else {
                $difficultyGuidance = "ADAPTIVE MODE: ADVANCED (accuracy: {$avgScore}%)\n"
                    . "- Minimal scaffolding — ask, then let them work\n"
                    . "- Challenge with harder variations and cross-topic connections\n"
                    . "- Focus on the tricky 1-2% cases: 'This trips up even 750+ scorers'\n"
                    . "- Discuss time allocation strategy and test psychology\n"
                    . "- Be concise — this student doesn't need hand-holding";
            }
        }

        $context = "\n\n<student_context>\n";
        $context .= "Name: " . ($user['name'] ?? 'Student') . "\n";
        $context .= "Target Score: " . ($user['target_score'] ?? 'Not set') . "\n";
        $context .= "Test Date: " . ($user['test_date'] ?? 'Not set');
        if ($daysLeft !== null) $context .= " ({$daysLeft} days away)";
        $context .= "\n";
        $context .= "Streak: " . ($streak['current_streak'] ?? 0) . " days\n";
        $context .= "Subscription: " . current_tier() . "\n";

        if ($avgScore !== null) {
            $context .= "Current Accuracy ({$subject}): {$avgScore}%\n";
            $context .= "Mastery Level: {$masteryLevel}\n";
        }
        if ($difficultyGuidance) {
            $context .= "\n{$difficultyGuidance}\n";
        }
        $context .= "</student_context>\n";

        // Fetch recent wrong answers to provide targeted context
        $recentWrong = Database::fetchAll(
            "SELECT qa.question_id, qa.user_answer, q.stem, q.correct_answer, q.topic, q.difficulty
             FROM question_answers qa
             JOIN questions q ON q.id = qa.question_id
             WHERE qa.user_id = ? AND qa.is_correct = 0
             ORDER BY qa.created_at DESC LIMIT 5",
            [$userId]
        );
        if ($recentWrong && count($recentWrong) > 0) {
            // Analyze mistake patterns
            $topicCounts = [];
            foreach ($recentWrong as $wrong) {
                $t = $wrong['topic'] ?? 'Unknown';
                $topicCounts[$t] = ($topicCounts[$t] ?? 0) + 1;
            }
            arsort($topicCounts);
            $repeatTopics = array_filter($topicCounts, fn($c) => $c >= 2);

            $context .= "\n<recent_mistakes>\n";
            $context .= "The student recently got these wrong. Use this to:\n";
            $context .= "- Identify error PATTERNS (not just individual mistakes)\n";
            $context .= "- Connect current discussion to these gaps when relevant\n";
            $context .= "- If they ask about a topic they've been getting wrong, start from where they're struggling\n";
            if ($repeatTopics) {
                $context .= "⚠ REPEAT ERROR PATTERN: Student has multiple recent mistakes in: " . implode(', ', array_keys($repeatTopics)) . ". Prioritize these.\n";
            }
            $context .= "\n";
            foreach ($recentWrong as $i => $wrong) {
                $num = $i + 1;
                $context .= "{$num}. Topic: {$wrong['topic']} | Difficulty: {$wrong['difficulty']}\n";
                $context .= "   Chose: {$wrong['user_answer']} | Correct: {$wrong['correct_answer']}\n";
                $context .= "   Q: " . mb_substr($wrong['stem'], 0, 200) . "\n";
            }
            $context .= "</recent_mistakes>\n";
        }

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
                    if (!$answer['is_correct']) {
                        $context .= "IMPORTANT: The student got this WRONG. Do NOT reveal the correct answer immediately. Use the Socratic method to guide them to discover why their answer was incorrect and what the right approach is.\n";
                    }
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