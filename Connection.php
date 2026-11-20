<?php
    require_once __DIR__ . '/config/bootstrap.php';

    $dbHost    = getenv('DB_HOST') ?: 'localhost';
    $dbPort    = getenv('DB_PORT') ?: '3306';
    $dbName    = getenv('DB_NAME') ?: 'connectionyou';
    $dbUser    = getenv('DB_USER') ?: 'connector_user';
    $dbPass    = getenv('DB_PASS') ?: '12345678';
    $dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";


    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // rowCount() = matched rows, so saving an unchanged record still reports success
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
    ];


    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (\PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        exit("Database connection failed. Please try again later.".  $e->getMessage());
    }
