<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AITutorHistory
 *  Wraps ai_tutor_conversations + ai_tutor_messages tables.
 *
 *  DB schema (actual):
 *  ai_tutor_conversations: id, user_id, subject_id, title, mode,
 *                          created_at, updated_at
 *  ai_tutor_messages:      id, conversation_id, role, content,
 *                          tokens_used, created_at
 * ═══════════════════════════════════════════════════════════════════
 */

class AITutorHistory
{
    // ─────────────────────────────────────────────────────────────────
    //  Table guard
    // ─────────────────────────────────────────────────────────────────
    private static function tablesExist(): bool
    {
        try {
            Database::fetchAll("SELECT 1 FROM ai_tutor_conversations LIMIT 1", []);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Recent conversations for the history sidebar
    //  Returns rows: id, title, mode, subject_id, last_message_at,
    //                created_at, subject (derived label)
    // ─────────────────────────────────────────────────────────────────
    public static function getRecent(int $userId, int $limit = 10): array
    {
        if (!self::tablesExist()) return [];

        try {
            $rows = Database::fetchAll(
                "SELECT c.id,
                        c.title,
                        c.mode,
                        c.subject_id,
                        c.created_at,
                        c.updated_at                       AS last_message_at
                 FROM ai_tutor_conversations c
                 WHERE c.user_id = ?
                 ORDER BY c.updated_at DESC
                 LIMIT ?",
                [$userId, $limit]
            );

            /* Map subject_id → slug used by the UI (math / reading_writing / general) */
            foreach ($rows as &$row) {
                $row['subject'] = self::subjectSlug((int)($row['subject_id'] ?? 0));
                /* Fallback title */
                if (empty($row['title'])) {
                    $row['title'] = ucfirst(str_replace('_', ' ', $row['mode'] ?? 'general'))
                                    . ' conversation';
                }
            }
            unset($row);

            return $rows;
        } catch (PDOException $e) {
            error_log('[AITutorHistory::getRecent] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  All messages for a specific conversation
    // ─────────────────────────────────────────────────────────────────
    public static function getMessages(int $conversationId): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT id, role, content, created_at
                 FROM ai_tutor_messages
                 WHERE conversation_id = ?
                 ORDER BY created_at ASC",
                [$conversationId]
            );
        } catch (PDOException $e) {
            error_log('[AITutorHistory::getMessages] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Create a new conversation row, return its id
    // ─────────────────────────────────────────────────────────────────
    public static function createConversation(int $userId, ?string $subject = null, string $mode = 'general'): int
    {
        if (!self::tablesExist()) return 0;

        try {
            return (int) Database::insert('ai_tutor_conversations', [
                'user_id'    => $userId,
                'subject_id' => self::subjectId($subject),
                'title'      => null,
                'mode'       => $mode,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            error_log('[AITutorHistory::createConversation] ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Save a single message to a conversation
    // ─────────────────────────────────────────────────────────────────
    public static function saveMessage(int $conversationId, string $role, string $content, ?int $tokens = null): int
    {
        if (!self::tablesExist()) return 0;

        try {
            $id = (int) Database::insert('ai_tutor_messages', [
                'conversation_id' => $conversationId,
                'role'            => $role,
                'content'         => $content,
                'tokens_used'     => $tokens,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            /* Bump conversation updated_at */
            Database::execute(
                "UPDATE ai_tutor_conversations SET updated_at = ? WHERE id = ?",
                [date('Y-m-d H:i:s'), $conversationId]
            );

            return $id;
        } catch (PDOException $e) {
            error_log('[AITutorHistory::saveMessage] ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Update conversation title (e.g. auto-generated from first message)
    // ─────────────────────────────────────────────────────────────────
    public static function updateTitle(int $conversationId, string $title): void
    {
        try {
            Database::execute(
                "UPDATE ai_tutor_conversations SET title = ? WHERE id = ?",
                [substr($title, 0, 255), $conversationId]
            );
        } catch (PDOException $e) {
            error_log('[AITutorHistory::updateTitle] ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Delete a conversation and its messages
    // ─────────────────────────────────────────────────────────────────
    public static function deleteConversation(int $conversationId, int $userId): bool
    {
        try {
            /* Verify ownership */
            $owner = Database::fetchColumn(
                "SELECT user_id FROM ai_tutor_conversations WHERE id = ?",
                [$conversationId]
            );
            if ((int)$owner !== $userId) return false;

            Database::execute("DELETE FROM ai_tutor_messages      WHERE conversation_id = ?", [$conversationId]);
            Database::execute("DELETE FROM ai_tutor_conversations WHERE id = ?",              [$conversationId]);
            return true;
        } catch (PDOException $e) {
            error_log('[AITutorHistory::deleteConversation] ' . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Recent messages across all conversations (for AITutor::ask context)
    // ─────────────────────────────────────────────────────────────────
    public static function getRecentMessages(int $userId, int $limit = 10): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT m.role, m.content, m.created_at
                 FROM ai_tutor_messages m
                 JOIN ai_tutor_conversations c ON c.id = m.conversation_id
                 WHERE c.user_id = ?
                 ORDER BY m.created_at DESC
                 LIMIT ?",
                [$userId, $limit]
            );
        } catch (PDOException $e) {
            error_log('[AITutorHistory::getRecentMessages] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers: subject_id ↔ slug mapping
    //  (Adjust IDs to match your subjects table if you have one)
    // ─────────────────────────────────────────────────────────────────
    private static function subjectSlug(int $subjectId): string
    {
        return match($subjectId) {
            1       => 'math',
            2       => 'reading_writing',
            default => 'general',
        };
    }

    private static function subjectId(?string $slug): ?int
    {
        return match($slug) {
            'math'            => 1,
            'reading_writing' => 2,
            default           => null,
        };
    }
}