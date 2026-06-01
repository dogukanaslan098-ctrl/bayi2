<?php
namespace Helpers;

/**
 * Veritabanı yardımcısı — PDO Singleton
 * Tüm DB işlemleri bu sınıf üzerinden yapılır.
 */
class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(): \PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHAR
            );
            self::$instance = new \PDO($dsn, DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$instance;
    }

    /** Hazırlıklı sorgu çalıştır */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Tek satır getir */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Tüm satırları getir */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Kayıt ekle — son insert ID döner */
    public static function insert(string $table, array $data): int
    {
        $cols  = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $sql   = "INSERT INTO `{$table}` ({$cols}) VALUES ({$marks})";
        self::query($sql, array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    /** Kayıt güncelle */
    public static function update(string $table, array $data, array $where): int
    {
        $set   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $cond  = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $sql   = "UPDATE `{$table}` SET {$set} WHERE {$cond}";
        $stmt  = self::query($sql, [...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    // ── Transaction ─────────────────────────────────────────
    public static function beginTransaction(): void { self::getInstance()->beginTransaction(); }
    public static function commit(): void           { self::getInstance()->commit(); }
    public static function rollback(): void         { self::getInstance()->rollBack(); }

    /** Scalar değer getir (COUNT, MAX, vb.) */
    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }
}
