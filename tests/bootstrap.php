<?php
    // PHPUnit bootstrap. Loads the app helpers (ecryptData, hashIdNumber, ...) into the test
    // process. The database and the PHP built-in server are only started by the Feature tests.

    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    $dbName = (string)(getenv('DB_NAME') ?: '');

    if (!preg_match('/_test$/', $dbName)) {
        fwrite(STDERR, "Refusing to run: DB_NAME ($dbName) must end in \"_test\". The suite wipes its tables.\n");
        exit(1);
    }

    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/Support/TestServer.php';
    require_once __DIR__ . '/Support/TestDatabase.php';
    require_once __DIR__ . '/Support/ApiTestCase.php';
