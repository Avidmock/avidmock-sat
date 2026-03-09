<?php
/**
 * AVIDMOCK · RateLimit
 * KEY FIX: check() now accepts 3 args (key, max, windowSeconds)
 * to match how api.php calls it: RateLimit::check(key, 60, 60)
 */
class RateLimit
{
    /**
     * Check if action is allowed.
     * @param string $key        Unique key for this action
     * @param int    $max        Maximum requests allowed in window
     * @param int    $windowSecs Time window in seconds (default 86400 = 1 day)
     */
    public static function check(string $key, int $max, int $windowSecs = 86400): bool
    {
        try {
            $count = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE limit_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$key, $windowSecs]
            );
            if ($count < $max) {
                self::increment($key);
                return true;
            }
            return false;
        } catch (Throwable $e) {
            error_log('RateLimit::check failed: ' . $e->getMessage());
            return true; // Fail open — never block users due to DB errors
        }
    }

    public static function increment(string $key): void
    {
        try {
            Database::insert('rate_limits', [
                'limit_key'  => $key,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('RateLimit::increment failed: ' . $e->getMessage());
        }
    }

    public static function remaining(string $key, int $max, int $windowSecs = 86400): int
    {
        try {
            $count = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE limit_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$key, $windowSecs]
            );
            return max(0, $max - $count);
        } catch (Throwable $e) {
            error_log('RateLimit::remaining failed: ' . $e->getMessage());
            return $max;
        }
    }

    public static function reset(string $key): void
    {
        try { Database::delete('rate_limits', ['limit_key' => $key]); }
        catch (Throwable $e) { error_log('RateLimit::reset failed: ' . $e->getMessage()); }
    }

    public static function cleanup(int $daysOld = 7): int
    {
        try {
            return Database::query(
                "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysOld]
            )->rowCount();
        } catch (Throwable $e) { error_log('RateLimit::cleanup failed: ' . $e->getMessage()); return 0; }
    }

    public static function throttle(string $key, int $maxPerMinute = 1): bool
    {
        return self::check($key, $maxPerMinute, 60);
    }
}