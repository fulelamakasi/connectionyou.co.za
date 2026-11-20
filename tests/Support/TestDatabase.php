<?php
    namespace Tests\Support;

    use PDO;
    use RuntimeException;

    /**
     * Builds the test schema from database/2_tables.sql (so it can't drift from the real one)
     * and resets it between tests.
     */
    final class TestDatabase
    {
        /** @var PDO|null */
        private static $pdo = null;

        public static function pdo(): PDO
        {
            if (self::$pdo === null) {
                self::$pdo = self::connect();
                self::createSchema(self::$pdo);
            }

            return self::$pdo;
        }

        public static function reset(): void
        {
            $pdo = self::pdo();
            $pdo->exec('TRUNCATE TABLE `user_logs`');
            $pdo->exec('TRUNCATE TABLE `users`');
        }

        private static function connect(): PDO
        {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME')
            );

            try {
                return new PDO($dsn, getenv('DB_USER') ?: '', getenv('DB_PASS') ?: '', [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (\PDOException $e) {
                throw new RuntimeException(
                    'Could not connect to the test database. Run tests/database/test_user.sql as a MySQL admin first. ' .
                    $e->getMessage()
                );
            }
        }

        private static function createSchema(PDO $pdo): void
        {
            $sql = file_get_contents(__DIR__ . '/../../database/2_tables.sql');
            $sql = preg_replace('/^\s*use\s+[^;]+;\s*$/mi', '', $sql);

            $pdo->exec('DROP TABLE IF EXISTS `user_logs`, `users`');

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $pdo->exec($statement);
            }
        }
    }
