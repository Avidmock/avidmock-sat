<?php
/**
 * =====================================================================
 *  AVIDMOCK SAT -- StudyRoom
 *  Real-time multiplayer quiz battles via server-side polling.
 *
 *  DATABASE TABLES:
 *
 *  CREATE TABLE study_rooms (
 *      id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      code            VARCHAR(6) NOT NULL UNIQUE,
 *      host_id         INT UNSIGNED NOT NULL,
 *      topic           VARCHAR(120) DEFAULT 'mixed',
 *      question_count  TINYINT UNSIGNED DEFAULT 10,
 *      time_per_question SMALLINT UNSIGNED DEFAULT 30,
 *      max_players     TINYINT UNSIGNED DEFAULT 10,
 *      status          ENUM('waiting','playing','finished','expired') DEFAULT 'waiting',
 *      is_public       TINYINT(1) DEFAULT 0,
 *      current_question_index TINYINT UNSIGNED DEFAULT 0,
 *      question_started_at DATETIME DEFAULT NULL,
 *      questions_json  MEDIUMTEXT DEFAULT NULL,
 *      created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      started_at      DATETIME DEFAULT NULL,
 *      ended_at        DATETIME DEFAULT NULL,
 *      INDEX idx_code (code),
 *      INDEX idx_status (status),
 *      INDEX idx_host (host_id)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE study_room_players (
 *      id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      room_id     INT UNSIGNED NOT NULL,
 *      user_id     INT UNSIGNED NOT NULL,
 *      score       INT DEFAULT 0,
 *      correct     INT DEFAULT 0,
 *      total_time_ms BIGINT DEFAULT 0,
 *      rank        TINYINT UNSIGNED DEFAULT NULL,
 *      joined_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY uq_room_user (room_id, user_id),
 *      INDEX idx_room (room_id)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE study_room_answers (
 *      id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      room_id         INT UNSIGNED NOT NULL,
 *      user_id         INT UNSIGNED NOT NULL,
 *      question_id     INT UNSIGNED NOT NULL,
 *      question_index  TINYINT UNSIGNED DEFAULT 0,
 *      answer          VARCHAR(1) NOT NULL,
 *      is_correct      TINYINT(1) DEFAULT 0,
 *      time_ms         INT UNSIGNED DEFAULT 0,
 *      points_earned   INT DEFAULT 0,
 *      answered_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY uq_room_user_q (room_id, user_id, question_id),
 *      INDEX idx_room_q (room_id, question_index)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * =====================================================================
 */

class StudyRoom
{
    // ── Constants ────────────────────────────────────────────────
    private const CODE_LENGTH       = 6;
    private const MAX_PLAYERS       = 10;
    private const EXPIRE_HOURS      = 2;
    private const BASE_POINTS       = 100;
    private const MAX_SPEED_BONUS   = 50;
    private const XP_PER_CORRECT    = 10;
    private const XP_WIN_BONUS      = 50;
    private const XP_PARTICIPATION  = 15;

    // ── Topics available ────────────────────────────────────────
    public static function getTopics(): array
    {
        return [
            'mixed'           => 'Mixed (All Topics)',
            'math_algebra'    => 'Math: Algebra',
            'math_advanced'   => 'Math: Advanced Math',
            'math_problem'    => 'Math: Problem Solving',
            'math_geometry'   => 'Math: Geometry & Trig',
            'rw_craft'        => 'R&W: Craft & Structure',
            'rw_information'  => 'R&W: Information & Ideas',
            'rw_conventions'  => 'R&W: Standard English',
            'rw_expression'   => 'R&W: Expression of Ideas',
        ];
    }

    // ── Generate unique 6-char room code ────────────────────────
    private static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxAttempts = 20;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Database::fetchColumn(
                "SELECT COUNT(*) FROM study_rooms WHERE code = ?", [$code]
            );
            if (!$exists) return $code;
        }
        throw new RuntimeException('Could not generate unique room code');
    }

    // ── Create a new room ───────────────────────────────────────
    public static function create(int $hostId, array $options = []): array
    {
        $code          = self::generateCode();
        $topic         = $options['topic'] ?? 'mixed';
        $questionCount = max(3, min(20, (int)($options['question_count'] ?? 10)));
        $timePer       = max(10, min(120, (int)($options['time_per_question'] ?? 30)));
        $maxPlayers    = max(2, min(self::MAX_PLAYERS, (int)($options['max_players'] ?? 10)));
        $isPublic      = !empty($options['is_public']) ? 1 : 0;

        // Fetch questions for the room
        $questions = self::fetchQuestions($topic, $questionCount);
        if (count($questions) < $questionCount) {
            $questionCount = count($questions);
        }
        if ($questionCount < 1) {
            throw new RuntimeException('No questions available for this topic');
        }

        $roomId = Database::insert('study_rooms', [
            'code'              => $code,
            'host_id'           => $hostId,
            'topic'             => $topic,
            'question_count'    => $questionCount,
            'time_per_question' => $timePer,
            'max_players'       => $maxPlayers,
            'is_public'         => $isPublic,
            'status'            => 'waiting',
            'questions_json'    => json_encode($questions),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        // Host auto-joins
        Database::insert('study_room_players', [
            'room_id'   => $roomId,
            'user_id'   => $hostId,
            'score'     => 0,
            'correct'   => 0,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'room_id' => $roomId,
            'code'    => $code,
            'topic'   => $topic,
        ];
    }

    // ── Fetch questions from DB ─────────────────────────────────
    private static function fetchQuestions(string $topic, int $count): array
    {
        $where = "WHERE q.is_active = 1";
        $params = [];

        if ($topic !== 'mixed') {
            $topicMap = [
                'math_algebra'   => ['math', 'algebra'],
                'math_advanced'  => ['math', 'advanced_math'],
                'math_problem'   => ['math', 'problem_solving'],
                'math_geometry'  => ['math', 'geometry_trigonometry'],
                'rw_craft'       => ['reading_writing', 'craft_structure'],
                'rw_information' => ['reading_writing', 'information_ideas'],
                'rw_conventions' => ['reading_writing', 'standard_english'],
                'rw_expression'  => ['reading_writing', 'expression_ideas'],
            ];

            if (isset($topicMap[$topic])) {
                $where .= " AND q.section = ? AND q.category = ?";
                $params[] = $topicMap[$topic][0];
                $params[] = $topicMap[$topic][1];
            }
        }

        // Only get multiple-choice questions with 4 options
        $where .= " AND q.question_type = 'multiple_choice'";

        try {
            $rows = Database::fetchAll(
                "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                        q.correct_answer, q.explanation, q.section, q.category, q.difficulty
                 FROM questions q
                 {$where}
                 ORDER BY RAND()
                 LIMIT ?",
                [...$params, $count]
            );
        } catch (\Throwable $e) {
            error_log('[StudyRoom::fetchQuestions] ' . $e->getMessage());
            $rows = [];
        }

        // If not enough questions from DB, generate placeholders
        if (count($rows) < $count) {
            $rows = self::generateFallbackQuestions($count);
        }

        return array_map(function ($q, $i) {
            return [
                'index'          => $i,
                'id'             => (int)($q['id'] ?? $i + 1),
                'question_text'  => $q['question_text'],
                'options'        => [
                    'A' => $q['option_a'],
                    'B' => $q['option_b'],
                    'C' => $q['option_c'],
                    'D' => $q['option_d'],
                ],
                'correct_answer' => strtoupper($q['correct_answer']),
                'explanation'    => $q['explanation'] ?? '',
                'section'        => $q['section'] ?? 'math',
                'difficulty'     => $q['difficulty'] ?? 'medium',
            ];
        }, $rows, array_keys($rows));
    }

    // ── Fallback questions if DB is empty ───────────────────────
    private static function generateFallbackQuestions(int $count): array
    {
        $pool = [
            ['question_text' => 'If 3x + 7 = 22, what is the value of x?', 'option_a' => '3', 'option_b' => '5', 'option_c' => '7', 'option_d' => '15', 'correct_answer' => 'B', 'explanation' => '3x = 22 - 7 = 15, x = 5', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90001],
            ['question_text' => 'What is the slope of the line y = -2x + 5?', 'option_a' => '5', 'option_b' => '2', 'option_c' => '-2', 'option_d' => '-5', 'correct_answer' => 'C', 'explanation' => 'In y = mx + b, the slope m = -2', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90002],
            ['question_text' => 'If f(x) = x^2 - 4, what is f(3)?', 'option_a' => '5', 'option_b' => '9', 'option_c' => '13', 'option_d' => '-1', 'correct_answer' => 'A', 'explanation' => 'f(3) = 9 - 4 = 5', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90003],
            ['question_text' => 'Which of the following is equivalent to (x+3)(x-3)?', 'option_a' => 'x^2 - 9', 'option_b' => 'x^2 + 9', 'option_c' => 'x^2 - 6x + 9', 'option_d' => 'x^2 + 6x + 9', 'correct_answer' => 'A', 'explanation' => 'Difference of squares: (a+b)(a-b) = a^2 - b^2', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'medium', 'id' => 90004],
            ['question_text' => 'A triangle has angles measuring 45, 90, and x degrees. What is x?', 'option_a' => '30', 'option_b' => '45', 'option_c' => '60', 'option_d' => '90', 'correct_answer' => 'B', 'explanation' => '180 - 45 - 90 = 45', 'section' => 'math', 'category' => 'geometry_trigonometry', 'difficulty' => 'easy', 'id' => 90005],
            ['question_text' => 'What is 15% of 200?', 'option_a' => '15', 'option_b' => '20', 'option_c' => '25', 'option_d' => '30', 'correct_answer' => 'D', 'explanation' => '0.15 x 200 = 30', 'section' => 'math', 'category' => 'problem_solving', 'difficulty' => 'easy', 'id' => 90006],
            ['question_text' => 'If 2^n = 64, what is n?', 'option_a' => '4', 'option_b' => '5', 'option_c' => '6', 'option_d' => '8', 'correct_answer' => 'C', 'explanation' => '2^6 = 64', 'section' => 'math', 'category' => 'advanced_math', 'difficulty' => 'medium', 'id' => 90007],
            ['question_text' => 'The median of {3, 7, 9, 12, 15} is:', 'option_a' => '7', 'option_b' => '9', 'option_c' => '10', 'option_d' => '12', 'correct_answer' => 'B', 'explanation' => 'Middle value of sorted set is 9', 'section' => 'math', 'category' => 'problem_solving', 'difficulty' => 'easy', 'id' => 90008],
            ['question_text' => 'Simplify: sqrt(144)', 'option_a' => '10', 'option_b' => '11', 'option_c' => '12', 'option_d' => '14', 'correct_answer' => 'C', 'explanation' => 'sqrt(144) = 12', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90009],
            ['question_text' => 'If a circle has radius 5, what is its area?', 'option_a' => '10pi', 'option_b' => '20pi', 'option_c' => '25pi', 'option_d' => '50pi', 'correct_answer' => 'C', 'explanation' => 'Area = pi*r^2 = 25pi', 'section' => 'math', 'category' => 'geometry_trigonometry', 'difficulty' => 'easy', 'id' => 90010],
            ['question_text' => 'Which word best completes: "The scientist\'s findings were ___ by later research."', 'option_a' => 'corroborated', 'option_b' => 'diminished', 'option_c' => 'ignored', 'option_d' => 'simplified', 'correct_answer' => 'A', 'explanation' => 'Corroborated means confirmed or supported', 'section' => 'reading_writing', 'category' => 'craft_structure', 'difficulty' => 'medium', 'id' => 90011],
            ['question_text' => 'What is the value of |(-7)|?', 'option_a' => '-7', 'option_b' => '0', 'option_c' => '7', 'option_d' => '49', 'correct_answer' => 'C', 'explanation' => 'Absolute value of -7 is 7', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90012],
            ['question_text' => 'Solve: 2(x - 3) = 10', 'option_a' => '4', 'option_b' => '5', 'option_c' => '7', 'option_d' => '8', 'correct_answer' => 'D', 'explanation' => '2x - 6 = 10, 2x = 16, x = 8', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90013],
            ['question_text' => 'If the ratio of boys to girls is 3:5 and there are 40 students total, how many boys are there?', 'option_a' => '12', 'option_b' => '15', 'option_c' => '20', 'option_d' => '25', 'correct_answer' => 'B', 'explanation' => '3/(3+5) * 40 = 15', 'section' => 'math', 'category' => 'problem_solving', 'difficulty' => 'medium', 'id' => 90014],
            ['question_text' => 'What is the y-intercept of y = 3x - 7?', 'option_a' => '3', 'option_b' => '-3', 'option_c' => '7', 'option_d' => '-7', 'correct_answer' => 'D', 'explanation' => 'In y = mx + b, the y-intercept b = -7', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90015],
            ['question_text' => 'Factor: x^2 + 5x + 6', 'option_a' => '(x+1)(x+6)', 'option_b' => '(x+2)(x+3)', 'option_c' => '(x+3)(x+3)', 'option_d' => '(x-2)(x-3)', 'correct_answer' => 'B', 'explanation' => '2 + 3 = 5, 2 * 3 = 6', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'medium', 'id' => 90016],
            ['question_text' => 'A car travels 180 miles in 3 hours. What is its average speed?', 'option_a' => '45 mph', 'option_b' => '50 mph', 'option_c' => '60 mph', 'option_d' => '90 mph', 'correct_answer' => 'C', 'explanation' => '180 / 3 = 60 mph', 'section' => 'math', 'category' => 'problem_solving', 'difficulty' => 'easy', 'id' => 90017],
            ['question_text' => 'What is the value of 3^0 + 3^1 + 3^2?', 'option_a' => '9', 'option_b' => '12', 'option_c' => '13', 'option_d' => '27', 'correct_answer' => 'C', 'explanation' => '1 + 3 + 9 = 13', 'section' => 'math', 'category' => 'advanced_math', 'difficulty' => 'easy', 'id' => 90018],
            ['question_text' => 'If 5x = 35, then x + 2 = ?', 'option_a' => '7', 'option_b' => '9', 'option_c' => '12', 'option_d' => '37', 'correct_answer' => 'B', 'explanation' => 'x = 7, so x + 2 = 9', 'section' => 'math', 'category' => 'algebra', 'difficulty' => 'easy', 'id' => 90019],
            ['question_text' => 'The perimeter of a square with side length 8 is:', 'option_a' => '16', 'option_b' => '24', 'option_c' => '32', 'option_d' => '64', 'correct_answer' => 'C', 'explanation' => '4 * 8 = 32', 'section' => 'math', 'category' => 'geometry_trigonometry', 'difficulty' => 'easy', 'id' => 90020],
        ];

        shuffle($pool);
        return array_slice($pool, 0, min($count, count($pool)));
    }

    // ── Join a room ─────────────────────────────────────────────
    public static function join(int $userId, string $roomCode): array
    {
        $roomCode = strtoupper(trim($roomCode));
        $room = Database::fetch(
            "SELECT * FROM study_rooms WHERE code = ? AND status IN ('waiting') LIMIT 1",
            [$roomCode]
        );

        if (!$room) {
            throw new RuntimeException('Room not found or game already started');
        }

        // Check expiry
        if (self::isExpired($room)) {
            Database::update('study_rooms', ['status' => 'expired'], ['id' => $room['id']]);
            throw new RuntimeException('This room has expired');
        }

        // Check player count
        $playerCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_players WHERE room_id = ?",
            [$room['id']]
        );

        if ($playerCount >= (int)$room['max_players']) {
            throw new RuntimeException('Room is full');
        }

        // Check if already joined
        $existing = Database::fetch(
            "SELECT id FROM study_room_players WHERE room_id = ? AND user_id = ?",
            [$room['id'], $userId]
        );

        if (!$existing) {
            Database::insert('study_room_players', [
                'room_id'   => $room['id'],
                'user_id'   => $userId,
                'score'     => 0,
                'correct'   => 0,
                'joined_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'room_id' => (int)$room['id'],
            'code'    => $room['code'],
            'status'  => $room['status'],
        ];
    }

    // ── Get full room state (polled by clients) ─────────────────
    public static function getState(string $roomCode): ?array
    {
        $roomCode = strtoupper(trim($roomCode));
        $room = Database::fetch(
            "SELECT * FROM study_rooms WHERE code = ?",
            [$roomCode]
        );
        if (!$room) return null;

        // Auto-expire check
        if (self::isExpired($room) && $room['status'] !== 'expired') {
            Database::update('study_rooms', ['status' => 'expired'], ['id' => $room['id']]);
            $room['status'] = 'expired';
        }

        // Auto-advance question if time has elapsed
        if ($room['status'] === 'playing') {
            $room = self::checkQuestionTimer($room);
        }

        $questions = json_decode($room['questions_json'] ?? '[]', true);
        $currentIdx = (int)($room['current_question_index'] ?? 0);

        // Get players with user info
        $players = Database::fetchAll(
            "SELECT srp.*, u.first_name, u.last_name, u.avatar_url
             FROM study_room_players srp
             JOIN users u ON u.id = srp.user_id
             WHERE srp.room_id = ?
             ORDER BY srp.score DESC, srp.correct DESC",
            [$room['id']]
        );

        $playerList = array_map(function ($p) {
            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: 'Student';
            return [
                'user_id'    => (int)$p['user_id'],
                'name'       => $name,
                'first_name' => $p['first_name'] ?? 'Student',
                'avatar_url' => $p['avatar_url'] ?? '',
                'score'      => (int)$p['score'],
                'correct'    => (int)$p['correct'],
                'rank'       => (int)($p['rank'] ?? 0),
            ];
        }, $players);

        // Build current question (hide correct answer for playing state)
        $currentQuestion = null;
        if ($room['status'] === 'playing' && isset($questions[$currentIdx])) {
            $q = $questions[$currentIdx];
            $currentQuestion = [
                'index'         => $currentIdx,
                'id'            => $q['id'],
                'question_text' => $q['question_text'],
                'options'       => $q['options'],
                'difficulty'    => $q['difficulty'] ?? 'medium',
                'section'       => $q['section'] ?? '',
            ];
        }

        // Get who has answered current question
        $answeredUsers = [];
        if ($room['status'] === 'playing' && isset($questions[$currentIdx])) {
            $answers = Database::fetchAll(
                "SELECT user_id FROM study_room_answers
                 WHERE room_id = ? AND question_index = ?",
                [$room['id'], $currentIdx]
            );
            $answeredUsers = array_column($answers, 'user_id');
        }

        // Calculate time remaining for current question
        $timeRemaining = null;
        if ($room['status'] === 'playing' && $room['question_started_at']) {
            $started = strtotime($room['question_started_at']);
            $elapsed = time() - $started;
            $timeRemaining = max(0, (int)$room['time_per_question'] - $elapsed);
        }

        // Get previous question results (for between-questions display)
        $previousResults = null;
        if ($room['status'] === 'playing' && $currentIdx > 0) {
            $prevIdx = $currentIdx - 1;
            $prevQ = $questions[$prevIdx] ?? null;
            if ($prevQ) {
                $prevAnswers = Database::fetchAll(
                    "SELECT sra.user_id, sra.answer, sra.is_correct, sra.points_earned, sra.time_ms,
                            u.first_name
                     FROM study_room_answers sra
                     JOIN users u ON u.id = sra.user_id
                     WHERE sra.room_id = ? AND sra.question_index = ?",
                    [$room['id'], $prevIdx]
                );
                $previousResults = [
                    'question_text'  => $prevQ['question_text'],
                    'correct_answer' => $prevQ['correct_answer'],
                    'explanation'    => $prevQ['explanation'] ?? '',
                    'answers'        => $prevAnswers,
                ];
            }
        }

        $topics = self::getTopics();

        return [
            'room_id'            => (int)$room['id'],
            'code'               => $room['code'],
            'host_id'            => (int)$room['host_id'],
            'topic'              => $room['topic'],
            'topic_label'        => $topics[$room['topic']] ?? $room['topic'],
            'question_count'     => (int)$room['question_count'],
            'time_per_question'  => (int)$room['time_per_question'],
            'max_players'        => (int)$room['max_players'],
            'status'             => $room['status'],
            'is_public'          => (bool)$room['is_public'],
            'current_question_index' => $currentIdx,
            'current_question'   => $currentQuestion,
            'time_remaining'     => $timeRemaining,
            'answered_users'     => array_map('intval', $answeredUsers),
            'players'            => $playerList,
            'player_count'       => count($playerList),
            'previous_results'   => $previousResults,
            'created_at'         => $room['created_at'],
            'started_at'         => $room['started_at'],
            'ended_at'           => $room['ended_at'],
        ];
    }

    // ── Start the game (host only) ──────────────────────────────
    public static function startGame(string $roomCode, int $userId): bool
    {
        $room = Database::fetch(
            "SELECT * FROM study_rooms WHERE code = ? AND status = 'waiting'",
            [strtoupper($roomCode)]
        );
        if (!$room) throw new RuntimeException('Room not found or already started');
        if ((int)$room['host_id'] !== $userId) {
            throw new RuntimeException('Only the host can start the game');
        }

        $playerCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_players WHERE room_id = ?",
            [$room['id']]
        );
        if ($playerCount < 1) {
            throw new RuntimeException('Need at least 1 player');
        }

        Database::update('study_rooms', [
            'status'               => 'playing',
            'started_at'           => date('Y-m-d H:i:s'),
            'current_question_index' => 0,
            'question_started_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $room['id']]);

        return true;
    }

    // ── Submit an answer ────────────────────────────────────────
    public static function submitAnswer(
        string $roomCode,
        int    $userId,
        int    $questionIndex,
        string $answer,
        int    $timeMs
    ): array {
        $roomCode = strtoupper($roomCode);
        $room = Database::fetch(
            "SELECT * FROM study_rooms WHERE code = ? AND status = 'playing'",
            [$roomCode]
        );
        if (!$room) throw new RuntimeException('Game not in progress');

        // Validate question index
        if ($questionIndex !== (int)$room['current_question_index']) {
            throw new RuntimeException('Wrong question index');
        }

        $questions = json_decode($room['questions_json'], true);
        $q = $questions[$questionIndex] ?? null;
        if (!$q) throw new RuntimeException('Question not found');

        // Check if already answered
        $existing = Database::fetch(
            "SELECT id FROM study_room_answers WHERE room_id = ? AND user_id = ? AND question_index = ?",
            [$room['id'], $userId, $questionIndex]
        );
        if ($existing) {
            throw new RuntimeException('Already answered this question');
        }

        // Check player is in room
        $player = Database::fetch(
            "SELECT id FROM study_room_players WHERE room_id = ? AND user_id = ?",
            [$room['id'], $userId]
        );
        if (!$player) throw new RuntimeException('Not in this room');

        $answer = strtoupper(trim($answer));
        $isCorrect = ($answer === strtoupper($q['correct_answer']));

        // Calculate points: base + speed bonus
        $points = 0;
        if ($isCorrect) {
            $maxTimeMs = (int)$room['time_per_question'] * 1000;
            $timeMs = max(0, min($maxTimeMs, $timeMs));
            $speedFraction = 1 - ($timeMs / max($maxTimeMs, 1));
            $speedBonus = (int)round(self::MAX_SPEED_BONUS * $speedFraction);
            $points = self::BASE_POINTS + $speedBonus;
        }

        // Record the answer
        Database::insert('study_room_answers', [
            'room_id'        => $room['id'],
            'user_id'        => $userId,
            'question_id'    => $q['id'],
            'question_index' => $questionIndex,
            'answer'         => $answer,
            'is_correct'     => $isCorrect ? 1 : 0,
            'time_ms'        => $timeMs,
            'points_earned'  => $points,
            'answered_at'    => date('Y-m-d H:i:s'),
        ]);

        // Update player score
        Database::execute(
            "UPDATE study_room_players
             SET score = score + ?, correct = correct + ?, total_time_ms = total_time_ms + ?
             WHERE room_id = ? AND user_id = ?",
            [$points, $isCorrect ? 1 : 0, $timeMs, $room['id'], $userId]
        );

        // Check if all players have answered -- auto-advance
        $playerCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_players WHERE room_id = ?",
            [$room['id']]
        );
        $answerCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_answers WHERE room_id = ? AND question_index = ?",
            [$room['id'], $questionIndex]
        );

        $allAnswered = ($answerCount >= $playerCount);
        if ($allAnswered) {
            self::advanceQuestion($room);
        }

        return [
            'is_correct'   => $isCorrect,
            'points'       => $points,
            'correct_answer' => $q['correct_answer'],
            'explanation'  => $q['explanation'] ?? '',
            'all_answered' => $allAnswered,
        ];
    }

    // ── Check question timer and auto-advance ───────────────────
    private static function checkQuestionTimer(array $room): array
    {
        if (!$room['question_started_at']) return $room;
        $elapsed = time() - strtotime($room['question_started_at']);
        $timePer = (int)$room['time_per_question'];

        // Give 3 extra seconds buffer for network latency
        if ($elapsed > $timePer + 3) {
            self::advanceQuestion($room);
            // Re-fetch room
            $room = Database::fetch("SELECT * FROM study_rooms WHERE id = ?", [$room['id']]);
        }
        return $room;
    }

    // ── Advance to next question or end game ────────────────────
    private static function advanceQuestion(array $room): void
    {
        $nextIdx = (int)$room['current_question_index'] + 1;
        $totalQuestions = (int)$room['question_count'];

        if ($nextIdx >= $totalQuestions) {
            // End the game
            self::finalizeGame($room);
        } else {
            Database::update('study_rooms', [
                'current_question_index' => $nextIdx,
                'question_started_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $room['id']]);
        }
    }

    // ── End game / finalize ─────────────────────────────────────
    public static function endGame(string $roomCode, int $userId = 0): bool
    {
        $room = Database::fetch(
            "SELECT * FROM study_rooms WHERE code = ?",
            [strtoupper($roomCode)]
        );
        if (!$room) return false;
        if ($room['status'] === 'finished') return true;

        // Only host or system can end
        if ($userId > 0 && (int)$room['host_id'] !== $userId) {
            throw new RuntimeException('Only the host can end the game');
        }

        self::finalizeGame($room);
        return true;
    }

    // ── Finalize game: ranks, XP ────────────────────────────────
    private static function finalizeGame(array $room): void
    {
        Database::update('study_rooms', [
            'status'   => 'finished',
            'ended_at' => date('Y-m-d H:i:s'),
        ], ['id' => $room['id']]);

        // Assign ranks
        $players = Database::fetchAll(
            "SELECT * FROM study_room_players WHERE room_id = ? ORDER BY score DESC, correct DESC, total_time_ms ASC",
            [$room['id']]
        );

        foreach ($players as $rank => $p) {
            $r = $rank + 1;
            Database::update('study_room_players', ['rank' => $r], ['id' => $p['id']]);

            // Award XP
            $xpEarned = self::XP_PARTICIPATION;
            $xpEarned += (int)$p['correct'] * self::XP_PER_CORRECT;
            if ($r === 1 && count($players) > 1) {
                $xpEarned += self::XP_WIN_BONUS;
            }

            try {
                // Update user_xp
                $existing = Database::fetch(
                    "SELECT id, xp FROM user_xp WHERE user_id = ?",
                    [$p['user_id']]
                );
                if ($existing) {
                    Database::execute(
                        "UPDATE user_xp SET xp = xp + ? WHERE user_id = ?",
                        [$xpEarned, $p['user_id']]
                    );
                } else {
                    Database::insert('user_xp', [
                        'user_id' => $p['user_id'],
                        'xp'      => $xpEarned,
                        'level'   => 1,
                    ]);
                }

                // Log XP event
                Database::insert('xp_events', [
                    'user_id'    => $p['user_id'],
                    'xp_earned'  => $xpEarned,
                    'event_type' => 'study_room',
                    'description'=> "Study room battle #{$room['code']} (Rank #{$r})",
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                error_log('[StudyRoom::finalizeGame] XP error: ' . $e->getMessage());
            }
        }
    }

    // ── Get leaderboard for a room ──────────────────────────────
    public static function getLeaderboard(string $roomCode): array
    {
        $room = Database::fetch(
            "SELECT id, question_count FROM study_rooms WHERE code = ?",
            [strtoupper($roomCode)]
        );
        if (!$room) return [];

        $players = Database::fetchAll(
            "SELECT srp.user_id, srp.score, srp.correct, srp.rank, srp.total_time_ms,
                    u.first_name, u.last_name, u.avatar_url
             FROM study_room_players srp
             JOIN users u ON u.id = srp.user_id
             WHERE srp.room_id = ?
             ORDER BY srp.score DESC, srp.correct DESC, srp.total_time_ms ASC",
            [$room['id']]
        );

        $totalQ = (int)$room['question_count'];

        return array_map(function ($p, $i) use ($totalQ) {
            $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: 'Student';
            return [
                'rank'       => $i + 1,
                'user_id'    => (int)$p['user_id'],
                'name'       => $name,
                'first_name' => $p['first_name'] ?? 'Student',
                'avatar_url' => $p['avatar_url'] ?? '',
                'score'      => (int)$p['score'],
                'correct'    => (int)$p['correct'],
                'accuracy'   => $totalQ > 0 ? round(((int)$p['correct'] / $totalQ) * 100) : 0,
                'avg_time'   => $totalQ > 0 ? round((int)$p['total_time_ms'] / $totalQ) : 0,
            ];
        }, $players, array_keys($players));
    }

    // ── List public active rooms ────────────────────────────────
    public static function getPublicRooms(int $limit = 20): array
    {
        self::expireOldRooms();

        $rooms = Database::fetchAll(
            "SELECT sr.*, u.first_name AS host_name,
                    (SELECT COUNT(*) FROM study_room_players WHERE room_id = sr.id) AS player_count
             FROM study_rooms sr
             JOIN users u ON u.id = sr.host_id
             WHERE sr.status = 'waiting' AND sr.is_public = 1
             ORDER BY sr.created_at DESC
             LIMIT ?",
            [$limit]
        );

        $topics = self::getTopics();

        return array_map(function ($r) use ($topics) {
            return [
                'code'           => $r['code'],
                'host_name'      => $r['host_name'] ?? 'Host',
                'topic'          => $r['topic'],
                'topic_label'    => $topics[$r['topic']] ?? $r['topic'],
                'question_count' => (int)$r['question_count'],
                'time_per_question' => (int)$r['time_per_question'],
                'player_count'   => (int)$r['player_count'],
                'max_players'    => (int)$r['max_players'],
                'created_at'     => $r['created_at'],
            ];
        }, $rooms);
    }

    // ── User's battle history ───────────────────────────────────
    public static function getUserHistory(int $userId, int $limit = 20): array
    {
        $rows = Database::fetchAll(
            "SELECT sr.code, sr.topic, sr.status, sr.question_count, sr.created_at, sr.ended_at,
                    srp.score, srp.correct, srp.rank,
                    (SELECT COUNT(*) FROM study_room_players WHERE room_id = sr.id) AS total_players
             FROM study_room_players srp
             JOIN study_rooms sr ON sr.id = srp.room_id
             WHERE srp.user_id = ?
             ORDER BY sr.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );

        $topics = self::getTopics();

        return array_map(function ($r) use ($topics) {
            return [
                'code'           => $r['code'],
                'topic'          => $r['topic'],
                'topic_label'    => $topics[$r['topic']] ?? $r['topic'],
                'status'         => $r['status'],
                'question_count' => (int)$r['question_count'],
                'score'          => (int)$r['score'],
                'correct'        => (int)$r['correct'],
                'rank'           => (int)($r['rank'] ?? 0),
                'total_players'  => (int)$r['total_players'],
                'won'            => (int)($r['rank'] ?? 0) === 1 && (int)$r['total_players'] > 1,
                'created_at'     => $r['created_at'],
                'ended_at'       => $r['ended_at'],
            ];
        }, $rows);
    }

    // ── User battle stats ───────────────────────────────────────
    public static function getUserStats(int $userId): array
    {
        $total = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_players srp
             JOIN study_rooms sr ON sr.id = srp.room_id
             WHERE srp.user_id = ? AND sr.status = 'finished'",
            [$userId]
        ) ?: 0;

        $wins = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM study_room_players srp
             JOIN study_rooms sr ON sr.id = srp.room_id
             WHERE srp.user_id = ? AND sr.status = 'finished' AND srp.rank = 1
               AND (SELECT COUNT(*) FROM study_room_players WHERE room_id = sr.id) > 1",
            [$userId]
        ) ?: 0;

        $bestStreak = 0;
        $currentStreak = 0;

        try {
            $history = Database::fetchAll(
                "SELECT srp.rank,
                        (SELECT COUNT(*) FROM study_room_players WHERE room_id = sr.id) AS pc
                 FROM study_room_players srp
                 JOIN study_rooms sr ON sr.id = srp.room_id
                 WHERE srp.user_id = ? AND sr.status = 'finished'
                 ORDER BY sr.ended_at ASC",
                [$userId]
            );

            $streak = 0;
            foreach ($history as $h) {
                if ((int)$h['rank'] === 1 && (int)$h['pc'] > 1) {
                    $streak++;
                    $bestStreak = max($bestStreak, $streak);
                } else {
                    $streak = 0;
                }
            }
            // Current streak from end
            $currentStreak = 0;
            foreach (array_reverse($history) as $h) {
                if ((int)$h['rank'] === 1 && (int)$h['pc'] > 1) {
                    $currentStreak++;
                } else {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'total_battles'  => $total,
            'wins'           => $wins,
            'losses'         => max(0, $total - $wins),
            'win_rate'       => $total > 0 ? round(($wins / $total) * 100) : 0,
            'best_streak'    => $bestStreak,
            'current_streak' => $currentStreak,
        ];
    }

    // ── Expire old rooms ────────────────────────────────────────
    private static function expireOldRooms(): void
    {
        try {
            Database::execute(
                "UPDATE study_rooms SET status = 'expired'
                 WHERE status IN ('waiting','playing')
                   AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)",
                [self::EXPIRE_HOURS]
            );
        } catch (\Throwable $e) {
            error_log('[StudyRoom::expireOldRooms] ' . $e->getMessage());
        }
    }

    // ── Check if room is expired ────────────────────────────────
    private static function isExpired(array $room): bool
    {
        $created = strtotime($room['created_at']);
        return (time() - $created) > (self::EXPIRE_HOURS * 3600);
    }
}
