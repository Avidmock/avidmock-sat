<?php
/**
 * Mock bootstrap — replaces config.php for local preview.
 * Provides fake Database, Auth, and all lib classes with sample data.
 * Usage: php -S localhost:8080 dev/router.php
 */

// ── Prevent re-inclusion ──
if (defined('AVIDMOCK_MOCK_LOADED')) return;
define('AVIDMOCK_MOCK_LOADED', true);

// ── Constants (same as config.php) ──
define('AVIDMOCK_ENV',   'development');
define('AVIDMOCK_DEBUG', true);
define('STUDENT_URL', 'http://localhost:8080');
define('ADMIN_URL',   'http://localhost:8081');
define('PUBLIC_URL',  'http://localhost:8080');
define('ASSETS_URL',  STUDENT_URL . '/assets');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'mock');
define('DB_USER', 'mock');
define('DB_PASS', 'mock');
define('DB_CHARSET', 'utf8mb4');

define('ANTHROPIC_API_KEY', '');
define('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929');
define('AI_TUTOR_MODEL', 'claude-sonnet-4-5-20250929');
define('AI_MAX_TOKENS', 2048);
define('AI_TEMPERATURE', 0.7);
define('AI_TUTOR_FREE_LIMIT', 5);
define('AI_TUTOR_PRO_LIMIT', 999);
define('AI_QUESTION_GEN_LIMIT', 50);

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', STUDENT_URL . '/auth/google-callback.php');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'hello@avidmock.com');
define('SMTP_FROM_NAME', 'Avidmock SAT');
define('STRIPE_PUBLIC_KEY', '');
define('STRIPE_SECRET_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');
define('PLAN_PRO_MONTHLY', 1499);
define('PLAN_PRO_YEARLY', 11988);
define('PLAN_FAMILY_MONTHLY', 2499);
define('PLAN_FAMILY_YEARLY', 19988);
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');
define('VAPID_SUBJECT', '');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('VIDEO_DIR', UPLOAD_DIR . '/videos');
define('IMAGE_DIR', UPLOAD_DIR . '/images');
define('EBOOK_DIR', UPLOAD_DIR . '/ebooks');
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('SESSION_LIFETIME', 86400 * 30);
define('SESSION_NAME', 'avidmock_session');
define('CSRF_TOKEN_NAME', 'avidmock_csrf');
define('COLOR_PRIMARY', '#1FE290');
define('COLOR_DARK', '#143230');
define('COLOR_AMBER', '#FFB347');
define('COLOR_CORAL', '#FF6B6B');
define('COLOR_BG', '#F0FAF4');
define('XP_QUIZ_COMPLETE', 50);
define('XP_PERFECT_SCORE', 100);
define('XP_STREAK_DAY', 25);
define('XP_AI_TUTOR_SESSION', 15);
define('XP_MICRO_LESSON', 20);
define('XP_PRACTICE_TEST', 200);
define('XP_WRITING_SUBMIT', 30);
define('LEAGUE_BRONZE_MIN', 0);
define('LEAGUE_SILVER_MIN', 300);
define('LEAGUE_GOLD_MIN', 750);
define('LEAGUE_DIAMOND_MIN', 1500);
define('SR_INITIAL_INTERVAL', 1);
define('SR_INITIAL_EASE', 2.5);
define('SR_MIN_EASE', 1.3);
define('SR_EASY_BONUS', 1.3);
define('SR_INTERVAL_MODIFIER', 1.0);
define('TIER_FREE', 'free');
define('TIER_PRO', 'pro');
define('TIER_FAMILY', 'family');
define('SAT_MIN_SCORE', 400);
define('SAT_MAX_SCORE', 1600);
define('SAT_SECTION_MIN', 200);
define('SAT_SECTION_MAX', 800);
define('SAT_MATH_QUESTIONS', 44);
define('SAT_RW_QUESTIONS', 54);
define('SAT_MATH_TIME', 70);
define('SAT_RW_TIME', 64);

$FEATURE_LIMITS = [
    TIER_FREE   => ['daily_questions'=>30,'ai_tutor_messages'=>5,'practice_tests'=>1,'writing_submissions'=>0,'adaptive_engine'=>'basic','spaced_repetition'=>false,'score_prediction'=>'basic','offline_mode'=>false,'parent_dashboard'=>false],
    TIER_PRO    => ['daily_questions'=>999,'ai_tutor_messages'=>999,'practice_tests'=>999,'writing_submissions'=>999,'adaptive_engine'=>'full','spaced_repetition'=>true,'score_prediction'=>'detailed','offline_mode'=>true,'parent_dashboard'=>false],
    TIER_FAMILY => ['daily_questions'=>999,'ai_tutor_messages'=>999,'practice_tests'=>999,'writing_submissions'=>999,'adaptive_engine'=>'full','spaced_repetition'=>true,'score_prediction'=>'detailed','offline_mode'=>true,'parent_dashboard'=>true,'max_accounts'=>3],
];

// ── Error display ──
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');

// ── Session ──
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ── Fake logged-in user ──
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Alex Johnson';
$_SESSION['user_email'] = 'alex@example.com';
$_SESSION['user_role'] = 'student';
$_SESSION['user_tier'] = TIER_PRO;
$_SESSION['onboarding_complete'] = true;
$_SESSION['test_date'] = date('Y-m-d', strtotime('+45 days'));
$_SESSION['target_score'] = 1400;
$_SESSION['logged_in_at'] = time();

// ── Global helpers (same as config.php) ──
function csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    return $_SESSION[CSRF_TOKEN_NAME];
}
function csrf_verify(): bool { return true; }
function e(string $str): string { return htmlspecialchars($str, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function json_response(array $data, int $code = 200): never { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; }
function get_feature_limits(string $tier = TIER_FREE): array { global $FEATURE_LIMITS; return $FEATURE_LIMITS[$tier] ?? $FEATURE_LIMITS[TIER_FREE]; }
function can_access(string $feature, string $tier = TIER_FREE): bool { $v = get_feature_limits($tier)[$feature] ?? false; return is_bool($v) ? $v : ($v > 0 || in_array($v, ['full','detailed'])); }
function time_ago(string $dt): string { $d = (new DateTime())->diff(new DateTime($dt)); if ($d->d > 0) return $d->d.'d ago'; if ($d->h > 0) return $d->h.'h ago'; return 'just now'; }
function current_tier(): string { return $_SESSION['user_tier'] ?? TIER_FREE; }
function current_user_id(): ?int { return $_SESSION['user_id'] ?? null; }
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool { return ($_SESSION['user_role'] ?? '') === 'admin'; }

// ── Mock PDO for global $pdo ──
$pdo = new class {
    public function prepare($sql) { return new class { public function execute($p=[]) {} public function fetch() { return null; } public function fetchAll() { return []; } public function fetchColumn() { return null; } public function rowCount() { return 0; } }; }
    public function beginTransaction() {}
    public function commit() {}
    public function rollBack() {}
    public function lastInsertId() { return '1'; }
};

// ══════════════════════════════════════════════════════════════════
//  MOCK DATABASE CLASS
// ══════════════════════════════════════════════════════════════════
class Database
{
    public static function connect(): object { global $pdo; return $pdo; }
    public static function query(string $sql, array $params = []): object { return new class { public function fetchAll() { return []; } public function fetch() { return null; } }; }
    public static function fetch(string $sql, array $params = []): ?array { return self::mockFetch($sql, $params); }
    public static function fetchAll(string $sql, array $params = []): array { return self::mockFetchAll($sql, $params); }
    public static function fetchColumn(string $sql, array $params = []): mixed { return self::mockFetchColumn($sql, $params); }
    public static function execute(string $sql, array $params = []): int { return 1; }
    public static function insert(string $table, array $data): int { return 1; }
    public static function update(string $table, array $data, array $where): int { return 1; }
    public static function delete(string $table, array $where): int { return 1; }
    public static function transaction(callable $cb): mixed { return $cb(); }
    public static function paginate(string $sql, array $p = [], int $pg = 1, int $pp = 25): array { return ['data'=>[],'total'=>0,'per_page'=>$pp,'current_page'=>$pg,'last_page'=>1]; }

    // ── Fake data router ──
    private static function mockFetch(string $sql, array $params): ?array
    {
        $sql = strtolower($sql);

        if (str_contains($sql, 'from users where')) {
            return [
                'id'=>1,'first_name'=>'Alex','last_name'=>'Johnson','name'=>'Alex Johnson',
                'email'=>'alex@example.com','role'=>'student','email_verified'=>1,
                'is_active'=>1,'is_suspended'=>0,'onboarding_complete'=>1,
                'test_date'=>date('Y-m-d',strtotime('+45 days')),'target_score'=>1400,
                'avatar_url'=>'','subscription_plan'=>'pro','current_streak'=>12,
                'longest_streak'=>28,'total_xp'=>875,'current_level'=>8,'league'=>'gold',
                'last_login_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d',strtotime('-3 months')),
                'updated_at'=>date('Y-m-d H:i:s'),'weekly_study_hours'=>10,
            ];
        }
        if (str_contains($sql, 'from user_xp')) {
            return ['user_id'=>1, 'xp'=>875, 'level'=>8];
        }
        if (str_contains($sql, 'from study_streaks')) {
            return ['user_id'=>1, 'current_streak'=>12, 'longest_streak'=>28, 'last_active'=>date('Y-m-d')];
        }
        if (str_contains($sql, 'from student_profiles')) {
            return ['user_id'=>1,'target_score'=>1400,'weekly_study_hours'=>10,'test_date'=>date('Y-m-d',strtotime('+45 days')),'onboarding_step'=>4,'onboarding_complete'=>1];
        }
        if (str_contains($sql, 'from practice_test_attempts')) {
            return ['id'=>1,'user_id'=>1,'total_score'=>1280,'math_score'=>650,'rw_score'=>630,'status'=>'submitted','submitted_at'=>date('Y-m-d',strtotime('-3 days'))];
        }
        if (str_contains($sql, 'from referral_codes')) {
            return ['id'=>1,'user_id'=>1,'code'=>'ALEX4821','uses'=>3,'created_at'=>date('Y-m-d',strtotime('-2 months'))];
        }
        if (str_contains($sql, 'from referral_events')) {
            return null;
        }
        if (str_contains($sql, 'from share_tokens')) {
            return null;
        }
        if (str_contains($sql, 'from diagnostic_attempts')) {
            return ['user_id'=>1,'math_theta'=>1.2,'rw_theta'=>0.8,'answers_json'=>'[]','completed_at'=>date('Y-m-d',strtotime('-3 months'))];
        }
        if (str_contains($sql, 'from category_performance')) {
            return ['id'=>1,'user_id'=>1,'category'=>'algebra','total_quizzes'=>15,'correct_answers'=>42,'avg_score'=>78,'mastery_level'=>'intermediate'];
        }
        if (str_contains($sql, 'from sat_quizzes')) {
            return ['id'=>1,'title'=>'Algebra Foundations','lesson_slug'=>'algebra-foundations','status'=>'published','time_limit'=>30];
        }
        if (str_contains($sql, 'from schedule_tasks')) {
            return ['id'=>1,'user_id'=>1,'task_date'=>date('Y-m-d'),'task_type'=>'quiz','title'=>'Linear Equations Review','duration_min'=>20,'is_completed'=>0];
        }
        if (str_contains($sql, 'from lessons')) {
            return ['id'=>1,'title'=>'Linear Equations','slug'=>'linear-equations','module_slug'=>'algebra','is_published'=>1,'duration_min'=>15];
        }
        if (str_contains($sql, 'study_rooms where') && str_contains($sql, 'code')) {
            return ['id'=>1,'code'=>'ABC123','host_id'=>1,'topic'=>'algebra','question_count'=>10,'time_per_question'=>30,'max_players'=>10,'status'=>'waiting','is_public'=>1,'current_question_index'=>0,'created_at'=>date('Y-m-d H:i:s')];
        }

        return null;
    }

    private static function mockFetchAll(string $sql, array $params): array
    {
        $sql = strtolower($sql);

        if (str_contains($sql, 'from user_xp') && str_contains($sql, 'order by')) {
            return self::fakeLeaderboard();
        }
        if (str_contains($sql, 'from xp_events') && str_contains($sql, 'group by')) {
            return self::fakeWeeklyLeaderboard();
        }
        if (str_contains($sql, 'from referral_events') && str_contains($sql, 'join users')) {
            return [
                ['first_name'=>'Sarah','created_at'=>date('Y-m-d',strtotime('-2 days'))],
                ['first_name'=>'Mike','created_at'=>date('Y-m-d',strtotime('-5 days'))],
                ['first_name'=>'Emma','created_at'=>date('Y-m-d',strtotime('-12 days'))],
            ];
        }
        if (str_contains($sql, 'from referral_codes') && str_contains($sql, 'order by')) {
            return [
                ['user_id'=>5,'uses'=>12,'first_name'=>'Jordan','avatar_url'=>''],
                ['user_id'=>3,'uses'=>8,'first_name'=>'Riley','avatar_url'=>''],
                ['user_id'=>7,'uses'=>5,'first_name'=>'Casey','avatar_url'=>''],
                ['user_id'=>1,'uses'=>3,'first_name'=>'Alex','avatar_url'=>''],
            ];
        }
        if (str_contains($sql, 'from achievements')) {
            return self::fakeAchievements();
        }
        if (str_contains($sql, 'from category_performance')) {
            return self::fakeCategoryPerformance();
        }
        if (str_contains($sql, 'from schedule_tasks')) {
            return self::fakeScheduleTasks();
        }
        if (str_contains($sql, 'study_rooms') && str_contains($sql, 'is_public')) {
            return self::fakePublicRooms();
        }
        if (str_contains($sql, 'study_room_players')) {
            return [];
        }
        if (str_contains($sql, 'from sat_quizzes')) {
            return [
                ['id'=>1,'title'=>'Algebra Foundations','lesson_slug'=>'algebra-foundations','status'=>'published','time_limit'=>30,'created_at'=>date('Y-m-d',strtotime('-10 days'))],
                ['id'=>2,'title'=>'Geometry Basics','lesson_slug'=>'geometry-basics','status'=>'published','time_limit'=>25,'created_at'=>date('Y-m-d',strtotime('-7 days'))],
            ];
        }

        return [];
    }

    private static function mockFetchColumn(string $sql, array $params): mixed
    {
        $sql = strtolower($sql);

        if (str_contains($sql, 'count(*)') && str_contains($sql, 'user_xp where xp >')) return 14;
        if (str_contains($sql, 'count(*)') && str_contains($sql, 'user_xp')) return 150;
        if (str_contains($sql, 'count(*)') && str_contains($sql, 'referral_events')) return 3;
        if (str_contains($sql, 'count(*)') && str_contains($sql, 'quiz_answers')) return 247;
        if (str_contains($sql, 'sum(xp_earned)')) return 185;
        if (str_contains($sql, 'coalesce(xp')) return 875;
        if (str_contains($sql, 'onboarding_step')) return 4;
        if (str_contains($sql, 'concat(first_name')) return 'Alex Johnson';

        return 0;
    }

    // ── Fake data generators ──

    private static function fakeLeaderboard(): array
    {
        $names = ['Jordan Lee','Riley Chen','Casey Kim','Morgan Davis','Alex Johnson','Taylor Wu','Sam Patel','Jamie Lin','Drew Garcia','Quinn Brown','Avery Scott','Blake Moore','Dakota Reyes','Emery Zhao','Finley Adams','Harper Liu','Jesse Clark','Logan Park','Reese White','Sage Thompson'];
        $data = [];
        foreach ($names as $i => $name) {
            $xp = 2500 - ($i * 120) + rand(-30,30);
            $data[] = [
                'user_id'=>$i+1,'total_xp'=>max(50,$xp),'current_level'=>max(1,intdiv($xp,200)),
                'name'=>$name,'avatar_url'=>'','current_streak'=>rand(0,30),
            ];
        }
        return $data;
    }

    private static function fakeWeeklyLeaderboard(): array
    {
        $top = self::fakeLeaderboard();
        shuffle($top);
        $top = array_slice($top, 0, 15);
        foreach ($top as &$r) {
            $r['weekly_xp'] = rand(80,450);
        }
        usort($top, fn($a,$b) => $b['weekly_xp'] - $a['weekly_xp']);
        return $top;
    }

    private static function fakeAchievements(): array
    {
        return [
            ['id'=>1,'slug'=>'onboarding_complete','name'=>'Getting Started','description'=>'Complete the onboarding flow','icon_url'=>'','badge_color'=>'#1fe290','xp_reward'=>50,'is_active'=>1,'unlocked'=>1,'unlocked_at'=>date('Y-m-d',strtotime('-3 months'))],
            ['id'=>2,'slug'=>'first_quiz','name'=>'Quiz Rookie','description'=>'Complete your first quiz','icon_url'=>'','badge_color'=>'#1fe290','xp_reward'=>50,'is_active'=>1,'unlocked'=>1,'unlocked_at'=>date('Y-m-d',strtotime('-2 months'))],
            ['id'=>3,'slug'=>'streak_7','name'=>'Week Warrior','description'=>'Maintain a 7-day streak','icon_url'=>'','badge_color'=>'#f5a623','xp_reward'=>100,'is_active'=>1,'unlocked'=>1,'unlocked_at'=>date('Y-m-d',strtotime('-1 month'))],
            ['id'=>4,'slug'=>'questions_50','name'=>'Half Century','description'=>'Answer 50 questions','icon_url'=>'','badge_color'=>'#1fe290','xp_reward'=>75,'is_active'=>1,'unlocked'=>1,'unlocked_at'=>date('Y-m-d',strtotime('-3 weeks'))],
            ['id'=>5,'slug'=>'first_perfect','name'=>'Perfection','description'=>'Get 100% on a quiz','icon_url'=>'','badge_color'=>'#f5a623','xp_reward'=>150,'is_active'=>1,'unlocked'=>0],
            ['id'=>6,'slug'=>'streak_30','name'=>'Monthly Master','description'=>'Maintain a 30-day streak','icon_url'=>'','badge_color'=>'#cd7f32','xp_reward'=>200,'is_active'=>1,'unlocked'=>0],
            ['id'=>7,'slug'=>'questions_200','name'=>'Practice Pro','description'=>'Answer 200 questions','icon_url'=>'','badge_color'=>'#a8a8b8','xp_reward'=>150,'is_active'=>1,'unlocked'=>0],
            ['id'=>8,'slug'=>'questions_500','name'=>'Question Machine','description'=>'Answer 500 questions','icon_url'=>'','badge_color'=>'#cd7f32','xp_reward'=>300,'is_active'=>1,'unlocked'=>0],
        ];
    }

    private static function fakeCategoryPerformance(): array
    {
        return [
            ['category'=>'Algebra','total_quizzes'=>15,'correct_answers'=>42,'avg_score'=>78,'mastery_level'=>'intermediate'],
            ['category'=>'Geometry','total_quizzes'=>8,'correct_answers'=>28,'avg_score'=>72,'mastery_level'=>'intermediate'],
            ['category'=>'Statistics','total_quizzes'=>6,'correct_answers'=>20,'avg_score'=>68,'mastery_level'=>'foundational'],
            ['category'=>'Advanced Math','total_quizzes'=>4,'correct_answers'=>10,'avg_score'=>55,'mastery_level'=>'foundational'],
        ];
    }

    private static function fakeScheduleTasks(): array
    {
        return [
            ['id'=>1,'task_date'=>date('Y-m-d'),'task_type'=>'lesson','title'=>'Linear Equations Review','duration_min'=>15,'is_completed'=>1,'sort_order'=>1],
            ['id'=>2,'task_date'=>date('Y-m-d'),'task_type'=>'quiz','title'=>'Algebra Quiz #3','duration_min'=>20,'is_completed'=>0,'sort_order'=>2],
            ['id'=>3,'task_date'=>date('Y-m-d'),'task_type'=>'review','title'=>'Spaced Review: Geometry','duration_min'=>10,'is_completed'=>0,'sort_order'=>3],
        ];
    }

    private static function fakePublicRooms(): array
    {
        return [
            ['id'=>1,'code'=>'ALG101','host_id'=>3,'topic'=>'algebra','topic_label'=>'Algebra','question_count'=>10,'time_per_question'=>30,'max_players'=>10,'status'=>'waiting','is_public'=>1,'created_at'=>date('Y-m-d H:i:s',strtotime('-5 min')),'player_count'=>3,'host_name'=>'Riley'],
            ['id'=>2,'code'=>'GEO202','host_id'=>7,'topic'=>'geometry','topic_label'=>'Geometry','question_count'=>8,'time_per_question'=>25,'max_players'=>6,'status'=>'waiting','is_public'=>1,'created_at'=>date('Y-m-d H:i:s',strtotime('-12 min')),'player_count'=>1,'host_name'=>'Casey'],
        ];
    }
}

// ══════════════════════════════════════════════════════════════════
//  MOCK AUTH CLASS
// ══════════════════════════════════════════════════════════════════
class Auth
{
    private static ?array $fakeUser = null;

    private static function user(): array
    {
        if (self::$fakeUser === null) {
            self::$fakeUser = Database::fetch("SELECT * FROM users WHERE id = ?", [1]);
        }
        return self::$fakeUser;
    }

    public static function requireStudent(): array { return self::user(); }
    public static function requireStudentOrRedirect(): array { return self::user(); }
    public static function optionalStudent(): ?array { return self::user(); }
    public static function requireStudentForOnboarding(): array { return self::user(); }
    public static function requireAdmin(): array { return self::user(); }
    public static function requireParent(): array { return self::user(); }
    public static function isLoggedIn(): bool { return true; }
    public static function isStudent(): bool { return true; }
    public static function isAdmin(): bool { return false; }
    public static function checkOnboardingComplete(int $uid): bool { return true; }
    public static function canAccess(string $f): bool { return true; }
    public static function daysUntilTest(): ?int { return 45; }
    public static function createSession(array $u): void {}
    public static function logout(): void {}
    public static function attempt(string $e, string $p): ?array { return self::user(); }
    public static function requireOnboarding(): void {}
}

// ══════════════════════════════════════════════════════════════════
//  MOCK LIB CLASSES
// ══════════════════════════════════════════════════════════════════

class User {
    public static function findById(int $id): ?array { return Database::fetch("SELECT * FROM users WHERE id = ?", [$id]); }
    public static function find(int $id): ?array { return self::findById($id); }
    public static function getStats(int $id): array { return ['total_quizzes'=>23,'correct_answers'=>168,'total_questions'=>247,'accuracy'=>68,'practice_tests'=>3,'study_time_hours'=>42,'avg_score'=>74,'best_score'=>92]; }
    public static function update(int $id, array $data): bool { return true; }
}

class StudyStreak {
    public static function getCurrent(int $uid): int { return 12; }
    public static function getLongest(int $uid): int { return 28; }
    public static function check(int $uid): array { return ['current_streak'=>12,'longest_streak'=>28,'last_active'=>date('Y-m-d'),'freeze_available'=>true]; }
    public static function get(int $uid): array { return ['current_streak'=>12,'longest_streak'=>28,'last_active'=>date('Y-m-d'),'freeze_available'=>true,'freezes_remaining'=>2]; }
    public static function record(int $uid): void {}
}

class Achievement {
    public static function getAll(int $uid): array { return Database::fetchAll("SELECT * FROM achievements"); }
    public static function unlock(int $uid, string $slug): bool { return true; }
    public static function getRecent(int $uid, int $limit = 5): array { return array_slice(Database::fetchAll("SELECT * FROM achievements"), 0, $limit); }
}

class Leaderboard {
    public static function getTop(int $limit = 50): array { return array_slice(Database::fetchAll("SELECT * FROM user_xp ORDER BY xp DESC"), 0, $limit); }
    public static function getWeeklyTop(int $limit = 50): array { return array_slice(Database::fetchAll("SELECT * FROM xp_events GROUP BY user_id ORDER BY weekly_xp DESC"), 0, $limit); }
    public static function getUserRank(int $uid): array { return ['rank'=>15,'total'=>150,'xp'=>875,'percentile'=>90]; }
    public static function getUserXP(int $uid): int { return 875; }
    public static function getNeighbors(int $uid): array {
        $all = self::getTop(20);
        return [
            'above' => array_slice($all, 12, 3),
            'user'  => ['user_id'=>$uid,'total_xp'=>875,'name'=>'Alex Johnson'],
            'below' => array_slice($all, 16, 3),
        ];
    }
}

class SocialShare {
    public static function generateScoreCard(int $uid): array {
        return ['user_id'=>$uid,'name'=>'Alex Johnson','first_name'=>'Alex','avatar_url'=>'','predicted_score'=>1280,'total_xp'=>875,'current_streak'=>12,'league'=>'gold','league_label'=>'Gold','league_color'=>'#f5a623','league_emoji'=>'','weekly_xp'=>185,'questions_practiced'=>247,'member_since'=>date('Y-m-d',strtotime('-3 months'))];
    }
    public static function generateShareUrl(int $uid, string $type = 'scorecard', array $extra = []): string { return 'mock_token_abc123'; }
    public static function getShareData(string $token): ?array { return ['user_id'=>1,'type'=>'scorecard','token'=>$token,'views'=>42,'data'=>[],'data_json'=>'{}']; }
    public static function getReferralCode(int $uid): string { return 'ALEX4821'; }
    public static function processReferral(string $code, int $newId): bool { return true; }
    public static function getReferralStats(int $uid): array {
        return ['code'=>'ALEX4821','total_referred'=>3,'active_referred'=>2,'rewards'=>[
            ['threshold'=>1,'type'=>'xp_bonus','label'=>'+100 XP','earned'=>true],
            ['threshold'=>3,'type'=>'pro_week','label'=>'1 Week Free Pro','earned'=>true],
            ['threshold'=>5,'type'=>'pro_month','label'=>'1 Month Free Pro','earned'=>false],
            ['threshold'=>10,'type'=>'pro_lifetime','label'=>'Free Pro for Life','earned'=>false],
        ],'next_reward'=>['threshold'=>5,'label'=>'1 Month Free Pro','remaining'=>2],'recent_referrals'=>[
            ['first_name'=>'Sarah','created_at'=>date('Y-m-d',strtotime('-2 days'))],
            ['first_name'=>'Mike','created_at'=>date('Y-m-d',strtotime('-5 days'))],
            ['first_name'=>'Emma','created_at'=>date('Y-m-d',strtotime('-12 days'))],
        ],'share_url'=>STUDENT_URL.'/referrals/?ref=ALEX4821'];
    }
    public static function getTopReferrers(int $limit = 10): array {
        return [['user_id'=>5,'uses'=>12,'first_name'=>'Jordan','avatar_url'=>''],['user_id'=>3,'uses'=>8,'first_name'=>'Riley','avatar_url'=>''],['user_id'=>7,'uses'=>5,'first_name'=>'Casey','avatar_url'=>''],['user_id'=>1,'uses'=>3,'first_name'=>'Alex','avatar_url'=>'']];
    }
    public static function getRewardTiers(): array { return [1=>['type'=>'xp_bonus','label'=>'+100 XP','xp'=>100],3=>['type'=>'pro_week','label'=>'1 Week Free Pro','xp'=>200],5=>['type'=>'pro_month','label'=>'1 Month Free Pro','xp'=>500],10=>['type'=>'pro_lifetime','label'=>'Free Pro for Life','xp'=>1000]]; }
    public static function getLeagueInfo(string $l): array { $leagues = ['bronze'=>['min'=>0,'label'=>'Bronze','color'=>'#cd7f32'],'silver'=>['min'=>300,'label'=>'Silver','color'=>'#a8a8b8'],'gold'=>['min'=>750,'label'=>'Gold','color'=>'#f5a623'],'diamond'=>['min'=>1500,'label'=>'Diamond','color'=>'#1fe290']]; return $leagues[$l] ?? $leagues['bronze']; }
}

class StudyRoom {
    public static function getTopics(): array { return ['mixed'=>'Mixed Topics','algebra'=>'Algebra','geometry'=>'Geometry','statistics'=>'Statistics','advanced'=>'Advanced Math']; }
    public static function getUserStats(int $uid): array { return ['total_games'=>8,'wins'=>3,'total_correct'=>42,'avg_score'=>78]; }
    public static function getUserHistory(int $uid, int $limit = 10): array {
        return [
            ['room_id'=>1,'code'=>'ALG101','topic'=>'algebra','topic_label'=>'Algebra','score'=>85,'rank'=>2,'player_count'=>4,'total_players'=>4,'played_at'=>date('Y-m-d',strtotime('-1 day')),'status'=>'finished','won'=>false],
            ['room_id'=>2,'code'=>'GEO202','topic'=>'geometry','topic_label'=>'Geometry','score'=>72,'rank'=>1,'player_count'=>3,'total_players'=>3,'played_at'=>date('Y-m-d',strtotime('-3 days')),'status'=>'finished','won'=>true],
        ];
    }
    public static function getPublicRooms(int $limit = 10): array { return Database::fetchAll("SELECT * FROM study_rooms WHERE is_public = 1"); }
    public static function create(array $d): array { return ['code'=>'NEW123','room_id'=>99]; }
    public static function join(string $code, int $uid): array { return ['success'=>true]; }
    public static function getState(string $code): ?array { return null; }
}

class Onboarding {
    public static function isComplete(int $uid): bool { return true; }
    public static function getCurrentStep(int $uid): string { return 'complete'; }
    public static function getProgressPercent(int $uid): int { return 100; }
    public static function getData(int $uid): array { return ['welcome_completed'=>true,'setup_completed'=>true,'diagnostic_completed'=>true,'plan_preview_completed'=>true,'setup'=>['test_date'=>date('Y-m-d',strtotime('+45 days')),'target_score'=>1400,'study_hours'=>10],'diagnostic'=>['math_theta'=>1.2,'rw_theta'=>0.8],'onboarding_step'=>4,'onboarding_complete'=>true]; }
    public static function saveStep(int $uid, string $step, array $data): void {}
    public static function markComplete(int $uid): void {}
    public static function processDiagnostic(int $uid, array $results): array { return ['math_score'=>75,'rw_score'=>68,'math_level'=>'intermediate','rw_level'=>'intermediate','math_theta'=>1.0,'rw_theta'=>0.6]; }
}

class CategoryPerformance {
    public static function getAll(int $uid): array { return Database::fetchAll("SELECT * FROM category_performance"); }
}

class ScorePredictor {
    public static function predict(int $uid): array { return ['predicted'=>1280,'low'=>1220,'high'=>1340,'confidence'=>0.72,'attempts'=>3]; }
}

class Schedule {
    public static function generate(int $uid): void {}
    public static function getToday(int $uid): array { return Database::fetchAll("SELECT * FROM schedule_tasks"); }
    public static function getRange(int $uid, string $from, string $to): array {
        $tasks = [];
        $d = new DateTime($from);
        $end = new DateTime($to);
        while ($d <= $end) {
            $date = $d->format('Y-m-d');
            $tasks[] = ['id'=>rand(1,999),'task_date'=>$date,'task_type'=>'lesson','title'=>'Review: Algebra','duration_min'=>20,'is_completed'=>($d < new DateTime()),'sort_order'=>1];
            if (rand(0,1)) $tasks[] = ['id'=>rand(1,999),'task_date'=>$date,'task_type'=>'quiz','title'=>'Practice Quiz','duration_min'=>15,'is_completed'=>false,'sort_order'=>2];
            $d->modify('+1 day');
        }
        return $tasks;
    }
    public static function get(int $uid): ?array { return ['id'=>1,'user_id'=>$uid,'start_date'=>date('Y-m-d'),'end_date'=>date('Y-m-d',strtotime('+30 days')),'test_date'=>date('Y-m-d',strtotime('+45 days')),'target_score'=>1400,'weekly_hours'=>10]; }
    public static function getUpcoming(int $uid, int $limit = 5): array { return self::getToday($uid); }
}

class Quiz {
    public static function getBySlug(string $slug): ?array { return Database::fetch("SELECT * FROM sat_quizzes WHERE lesson_slug = ?", [$slug]); }
    public static function getAll(): array { return Database::fetchAll("SELECT * FROM sat_quizzes"); }
}

class Question {
    public static function getByQuizId(int $id): array { return []; }
}

class Lesson {
    public static function getBySlug(string $slug): ?array { return Database::fetch("SELECT * FROM lessons"); }
}

class Session {
    public static function getUpcoming(int $uid, int $limit = 5): array { return []; }
    public static function getPast(int $uid, int $limit = 10): array { return []; }
    public static function getActive(int $uid): ?array { return null; }
}
class Mailer { public static function send(string $to, string $sub, string $body): bool { return true; } }
class QuizAttempt { public static function getLatest(int $uid, int $qid): ?array { return null; } }
class QuizProgress { public static function get(int $uid, int $qid): ?array { return null; } }
class QuestionStats { }
class StudentPerformance { public static function get(int $uid): array { return []; } }
class WeeklyPerformance { public static function get(int $uid): array { return []; } }
class RateLimit { public static function check(string $key, int $max = 10, int $window = 3600): bool { return true; } }
class SEO { public static function title(string $t): string { return $t . ' | AvidMock SAT'; } public static function meta(): string { return ''; } }
class VideoProgress { public static function get(int $uid, int $lid): ?array { return null; } }
class AnswerChecker { public static function check(int $qid, string $ans): array { return ['correct'=>true,'explanation'=>'Great job!']; } }
class ExplanationFeedback { }
class SmartNotebook {
    public static function addScan(int $uid, string $img, ?array $ai = null): array { return ['id'=>1,'success'=>true]; }
    public static function addText(int $uid, string $text, string $folder = 'General'): array { return ['id'=>1,'success'=>true]; }
    public static function getEntries(int $uid, array $filters = []): array {
        return [
            ['id'=>1,'user_id'=>$uid,'type'=>'text','content'=>'Remember: quadratic formula is x = (-b +/- sqrt(b^2-4ac)) / 2a','folder'=>'Algebra','is_starred'=>true,'created_at'=>date('Y-m-d',strtotime('-2 days'))],
            ['id'=>2,'user_id'=>$uid,'type'=>'text','content'=>'SOH-CAH-TOA for trig ratios','folder'=>'Geometry','is_starred'=>false,'created_at'=>date('Y-m-d',strtotime('-5 days'))],
            ['id'=>3,'user_id'=>$uid,'type'=>'text','content'=>'For probability: P(A or B) = P(A) + P(B) - P(A and B)','folder'=>'Statistics','is_starred'=>true,'created_at'=>date('Y-m-d',strtotime('-1 week'))],
        ];
    }
    public static function getFolders(int $uid): array { return [['name'=>'General','count'=>1],['name'=>'Algebra','count'=>5],['name'=>'Geometry','count'=>3],['name'=>'Statistics','count'=>2],['name'=>'Advanced Math','count'=>0]]; }
    public static function toggleStar(int $uid, int $id): bool { return true; }
    public static function delete(int $uid, int $id): bool { return true; }
    public static function getDomainStats(int $uid): array { return ['Algebra'=>5,'Geometry'=>3,'Statistics'=>2,'General'=>1]; }
    public static function getRecommendations(int $uid, int $limit = 5): array { return []; }
}

class AITutor { }
class AITutorHistory {
    public static function getConversations(int $uid): array { return []; }
    public static function getRecent(int $uid, int $limit = 5): array {
        return [
            ['id'=>1,'title'=>'Help with quadratic equations','subject'=>'algebra','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'message_count'=>8],
            ['id'=>2,'title'=>'Geometry circle theorems','subject'=>'geometry','created_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'message_count'=>5],
        ];
    }
    public static function getConversation(int $uid, int $id): ?array { return ['id'=>$id,'title'=>'Help with quadratic equations','messages'=>[]]; }
}

class AdaptiveEngine {
    public static function pCorrect(float $a, float $d, float $disc = 1.0): float { return 0.7; }
    public static function getState(int $uid, string $domain = 'overall'): array { return ['user_id'=>$uid,'domain'=>$domain,'ability_estimate'=>0.8,'questions_answered'=>47]; }
    public static function getAllStates(int $uid): array { return [self::getState($uid,'algebra'),self::getState($uid,'geometry'),self::getState($uid,'statistics')]; }
    public static function getNextQuestion(int $uid, ?string $d = null): ?array { return ['id'=>1,'stem'=>'If 3x + 7 = 22, what is x?','option_a'=>'3','option_b'=>'5','option_c'=>'7','option_d'=>'15','correct_answer'=>'B','difficulty'=>0.5,'domain'=>'algebra']; }
    public static function updateAbility(int $uid, int $qid, bool $c, int $t): array { return ['ability'=>0.85,'delta'=>0.05]; }
    public static function getStudentProfile(int $uid): array { return ['ability'=>0.8,'strengths'=>['algebra'],'weaknesses'=>['statistics'],'recommended_domain'=>'statistics']; }
    public static function generateAdaptiveQuiz(int $uid, int $count = 10, ?string $d = null): array { return ['quiz_id'=>1,'questions'=>[]]; }
    public static function predictFromAbility(int $uid, float $a): array { return ['predicted_score'=>1280,'low'=>1220,'high'=>1340]; }
    public static function getDomains(): array { return ['algebra'=>'Algebra','geometry'=>'Geometry','statistics'=>'Statistics','advanced'=>'Advanced Math']; }
    public static function getResponseHistory(int $uid, int $limit = 50): array { return []; }
    public static function getSessionStats(int $uid, string $since = null): array { return ['total_questions'=>47,'correct'=>32,'accuracy'=>68,'avg_time'=>35]; }
}

class WeaknessAnalyzer {
    public static function analyze(int $uid): array { return ['weakest_domains'=>[['domain'=>'Statistics','score'=>55,'recommendation'=>'Focus on probability and data analysis']],'strongest_domains'=>[['domain'=>'Algebra','score'=>78]]]; }
}

class MathVision {
    public static function analyze(string $img): array { return ['problem'=>'2x + 5 = 15','solution'=>'x = 5','steps'=>['Subtract 5 from both sides','Divide by 2']]; }
}

class AIEssayScorer {
    public static function score(string $text): array { return ['score'=>7,'max'=>10,'feedback'=>'Good structure']; }
}

class ParentReport {
    public static function generate(int $uid): array { return ['student_name'=>'Alex','weekly_study_time'=>'8h 30m','quizzes_completed'=>5,'streak'=>12,'predicted_score'=>1280]; }
}

class ScorePredictor2 {
    public static function predict(int $uid): array { return ['predicted'=>1280,'low'=>1220,'high'=>1340,'confidence'=>0.72]; }
    public static function getScoreHistory(int $uid, int $limit = 10): array {
        return [
            ['date'=>date('Y-m-d',strtotime('-30 days')),'predicted'=>1150],
            ['date'=>date('Y-m-d',strtotime('-21 days')),'predicted'=>1200],
            ['date'=>date('Y-m-d',strtotime('-14 days')),'predicted'=>1240],
            ['date'=>date('Y-m-d',strtotime('-7 days')),'predicted'=>1260],
            ['date'=>date('Y-m-d'),'predicted'=>1280],
        ];
    }
}

// Suppress autoloader from trying to load real classes
spl_autoload_register(function (string $class): void {
    // Already mocked — do nothing
}, true, true);
