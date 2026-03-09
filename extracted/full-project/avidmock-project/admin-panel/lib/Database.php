<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Database
 *  Thin PDO wrapper used by all lib classes.
 * ═══════════════════════════════════════════════════════════════════
 */

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $stmt = self::connect()->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})");
        $stmt->execute(array_values($data));
        return (int) self::connect()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $stmt = self::connect()->prepare("UPDATE `{$table}` SET {$set} WHERE {$cond}");
        $stmt->execute([...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $stmt = self::connect()->prepare("DELETE FROM `{$table}` WHERE {$cond}");
        $stmt->execute(array_values($where));
        return $stmt->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connect();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 25): array
    {
        // Get total count
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _count_query";
        $total = (int) self::fetchColumn($countSql, $params);

        // Get paginated rows
        $offset = ($page - 1) * $perPage;
        $rows = self::fetchAll("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ];
    }
}