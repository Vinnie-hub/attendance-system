<?php
// includes/db.php  – PDO singleton

require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never leak credentials in production
                error_log('DB Connection failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }
}

/**
 * Convenience wrapper: DB::get()->prepare(...)
 * Usage: db_query("SELECT * FROM users WHERE id = ?", [$id])
 */
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = DB::get()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
