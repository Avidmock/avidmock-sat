<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · SmartNotebook
 *  Students photograph homework/textbook problems → AI identifies them,
 *  maps to SAT curriculum, and generates correlated practice.
 *  The bridge between school math and SAT prep.
 * ═══════════════════════════════════════════════════════════════════
 *
 * Tables needed:
 *   notebook_entries (id, user_id, type ENUM('scan','text','bookmark'),
 *     source_image TEXT, recognized_text TEXT, solution_json TEXT,
 *     sat_domain VARCHAR(50), sat_skill VARCHAR(80), difficulty VARCHAR(20),
 *     tags JSON, is_starred BOOLEAN DEFAULT 0, folder VARCHAR(50) DEFAULT 'General',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
 *
 *   notebook_folders (id, user_id, name VARCHAR(50), color VARCHAR(7), icon VARCHAR(20),
 *     entry_count INT DEFAULT 0, created_at TIMESTAMP)
 */

class SmartNotebook
{
    /**
     * Add a scanned entry (from extension or upload).
     */
    public static function addScan(int $userId, string $base64Image, ?array $aiResult = null): array
    {
        $db = Database::connect();

        $entry = [
            'user_id'         => $userId,
            'type'            => 'scan',
            'source_image'    => $base64Image,
            'recognized_text' => $aiResult['problem'] ?? '',
            'solution_json'   => json_encode($aiResult['solution'] ?? []),
            'sat_domain'      => $aiResult['sat_mapping']['domain_key'] ?? '',
            'sat_skill'       => $aiResult['sat_mapping']['skill_key'] ?? '',
            'difficulty'      => $aiResult['difficulty'] ?? 'medium',
            'tags'            => json_encode($aiResult['tags'] ?? []),
        ];

        try {
            $stmt = $db->prepare(
                "INSERT INTO notebook_entries
                 (user_id, type, source_image, recognized_text, solution_json, sat_domain, sat_skill, difficulty, tags)
                 VALUES (:user_id, :type, :source_image, :recognized_text, :solution_json, :sat_domain, :sat_skill, :difficulty, :tags)"
            );
            $stmt->execute($entry);
            $entry['id'] = (int) $db->lastInsertId();
            return ['success' => true, 'entry' => $entry];
        } catch (\Throwable $e) {
            error_log("SmartNotebook::addScan error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save entry'];
        }
    }

    /**
     * Add a text/bookmark entry.
     */
    public static function addText(int $userId, string $text, string $folder = 'General'): array
    {
        $db = Database::connect();
        try {
            $stmt = $db->prepare(
                "INSERT INTO notebook_entries (user_id, type, recognized_text, folder)
                 VALUES (?, 'text', ?, ?)"
            );
            $stmt->execute([$userId, $text, $folder]);
            return ['success' => true, 'id' => (int) $db->lastInsertId()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get notebook entries with pagination.
     */
    public static function getEntries(int $userId, array $filters = []): array
    {
        $db = Database::connect();
        $where  = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['folder'])) {
            $where[]  = 'folder = ?';
            $params[] = $filters['folder'];
        }
        if (!empty($filters['domain'])) {
            $where[]  = 'sat_domain = ?';
            $params[] = $filters['domain'];
        }
        if (!empty($filters['starred'])) {
            $where[] = 'is_starred = 1';
        }
        if (!empty($filters['type'])) {
            $where[]  = 'type = ?';
            $params[] = $filters['type'];
        }

        $limit  = (int)($filters['limit']  ?? 20);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "SELECT id, type, recognized_text, solution_json, sat_domain, sat_skill,
                       difficulty, tags, is_starred, folder, created_at
                FROM notebook_entries
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($entries as &$e) {
                $e['solution'] = json_decode($e['solution_json'] ?? '{}', true);
                $e['tags']     = json_decode($e['tags'] ?? '[]', true);
                unset($e['solution_json']);
            }

            // Get total count
            $countSql = "SELECT COUNT(*) FROM notebook_entries WHERE " . implode(' AND ', $where);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            return ['entries' => $entries, 'total' => $total];
        } catch (\Throwable $e) {
            return ['entries' => [], 'total' => 0];
        }
    }

    /**
     * Get folders for a user.
     */
    public static function getFolders(int $userId): array
    {
        $db = Database::connect();
        try {
            return $db->prepare(
                "SELECT folder as name, COUNT(*) as count
                 FROM notebook_entries WHERE user_id = ? GROUP BY folder ORDER BY count DESC"
            )->execute([$userId]) ? $db->query("SELECT folder as name, COUNT(*) as count FROM notebook_entries WHERE user_id = {$userId} GROUP BY folder ORDER BY count DESC")->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [['name' => 'General', 'count' => 0]];
        }
    }

    /**
     * Toggle star on an entry.
     */
    public static function toggleStar(int $userId, int $entryId): bool
    {
        $db = Database::connect();
        try {
            $stmt = $db->prepare(
                "UPDATE notebook_entries SET is_starred = NOT is_starred WHERE id = ? AND user_id = ?"
            );
            return $stmt->execute([$entryId, $userId]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete an entry.
     */
    public static function delete(int $userId, int $entryId): bool
    {
        $db = Database::connect();
        try {
            $stmt = $db->prepare("DELETE FROM notebook_entries WHERE id = ? AND user_id = ?");
            return $stmt->execute([$entryId, $userId]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get SAT domain stats from notebook entries.
     */
    public static function getDomainStats(int $userId): array
    {
        $db = Database::connect();
        try {
            $stmt = $db->prepare(
                "SELECT sat_domain, COUNT(*) as count,
                        SUM(CASE WHEN difficulty='easy' THEN 1 ELSE 0 END) as easy,
                        SUM(CASE WHEN difficulty='medium' THEN 1 ELSE 0 END) as medium,
                        SUM(CASE WHEN difficulty='hard' THEN 1 ELSE 0 END) as hard
                 FROM notebook_entries
                 WHERE user_id = ? AND sat_domain != ''
                 GROUP BY sat_domain"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Generate practice recommendations based on notebook entries.
     */
    public static function getRecommendations(int $userId, int $limit = 5): array
    {
        $stats = self::getDomainStats($userId);
        if (empty($stats)) return [];

        // Find most scanned domains → suggest quizzes
        $db = Database::connect();
        $recommendations = [];

        foreach ($stats as $s) {
            if (empty($s['sat_domain'])) continue;
            try {
                $quizzes = $db->prepare(
                    "SELECT id, title, lesson_slug FROM sat_quizzes
                     WHERE status = 'published' AND lesson_slug LIKE ?
                     LIMIT 3"
                );
                $quizzes->execute(['%' . $s['sat_domain'] . '%']);
                $found = $quizzes->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($found as $q) {
                    $recommendations[] = [
                        'quiz_id'   => $q['id'],
                        'title'     => $q['title'],
                        'domain'    => $s['sat_domain'],
                        'reason'    => "You've scanned {$s['count']} problems in this area",
                        'scan_count'=> (int)$s['count'],
                    ];
                }
            } catch (\Throwable) {}
        }

        usort($recommendations, fn($a, $b) => $b['scan_count'] - $a['scan_count']);
        return array_slice($recommendations, 0, $limit);
    }
}
