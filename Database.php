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
    ];


    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (\PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        exit("Database connection failed. Please try again later.".  $e->getMessage());
    }

    function getSelectQuery($whereClause, $whereClauseColumn = '', $whereClauseValue = '', $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    `id`, 
                    `email`, 
                    `first_name`, 
                    `last_name`, 
                    `name`, 
                    `phone_number`, 
                    `id_number_hash`, 
                    `message`, 
                    `is_active`, 
                    `created_at`, 
                    `updated_at`,
                    `deleted_at`,
                    `editedby`
                FROM 
                    `connectionyou`.`users` 
                WHERE
                    $whereClause
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);

        if ($whereClauseColumn != NULL) {
            $stmt->bindValue($whereClauseColumn, $whereClauseValue);
        }

        $stmt->bindValue(':limit', $limit);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getActiveUsers($is_active, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `is_active` = :is_active
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUsersByCreatedAt($created_at, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE DATE(`created_at`) = :created_at
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByEmail($email, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `email` = :email
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserById($id)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `id` = :id
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByIdNumber($id_number_hash, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `id_number_hash` = :id_number_hash
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_number_hash', $id_number_hash, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByPhoneNumber($phone_number, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `phone_number` = :phone_number
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByName($name, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `name` = :name
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getAllUsers($limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;        
    }

    function getAllUsersNonDeleted($limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `deleted_at` IS NULL
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getAllUsersDeleted($limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `deleted_at` IS NOT NULL
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByFirstName($first_name, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `first_name` = :first_name
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function getUserByLastName($last_name, $limit = 1000)
    {
        global $pdo;
        $sql = "SELECT 
                    *
                FROM `connectionyou`.`users` 
                WHERE `last_name` = :last_name
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll();

        if ($users === null) {
            return FALSE;
        }

        return $users;
    }

    function setPerformUserInsert($first_name, $last_name, $name, $phone_number, $id_number_hash, $id_number_encrypted, $email, $message, $edited_by)
    {
        global $pdo;
        $sql = "INSERT INTO `connectionyou`.`users`(`email`, `first_name`, `last_name`, `name`, `phone_number`, `id_number_hash`, `id_number_encrypted`, `message`, `editedby`) 
                VALUES (:email, :first_name, :last_name, :name, :phone_number, :id_number_hash, :id_number_encrypted, :message, :edited_by)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':email'        => $email,
            ':first_name'   => $first_name,
            ':last_name'    => $last_name,
            ':name'    => $name,
            ':phone_number' => $phone_number,
            ':id_number_hash' => $id_number_hash,
            ':id_number_encrypted' => $id_number_encrypted,
            ':message' => $message,
            ':edited_by' => $edited_by,
        ]);

        if ($stmt->rowCount() > 0) {
            return $pdo->lastInsertId();
        }

        return false;
    }

    function setUpdateRecord($user_id, $first_name, $last_name, $name, $phone_number, $id_number_hash, $id_number_encrypted, $email, $message, $edited_by)
    {
        global $pdo;
        $sql = "UPDATE `connectionyou`.`users` 
                SET 
                    `email` = :email,
                    `first_name` = :first_name,
                    `last_name` = :last_name,
                    `name` = :name,
                    `phone_number` = :phone_number,
                    `id_number_hash` = :id_number_hash,
                    `id_number_encrypted` = :id_number_encrypted,
                    `message` = :message,
                    `updated_at` = NOW(),
                    `editedby` = :edited_by
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email'        => $email,
            ':first_name'   => $first_name,
            ':last_name'    => $last_name,
            ':name'    => $name,
            ':phone_number' => $phone_number,
            ':id_number_hash' => $id_number_hash,
            ':id_number_encrypted' => $id_number_encrypted,
            ':message' => $message,
            ':edited_by' => $edited_by,
            ':id'           => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            return "User updated successfully.";
        }

        return false;
    }

    function setUserStatus($user_id, $is_active, $edited_by)
    {
        global $pdo;
        $active = 'Activated';

        if(!$is_active) {
            $active = 'Deactivated';
        }

        $sql = "UPDATE `connectionyou`.`users` 
                SET 
                    `is_active` = :is_active,
                    `updated_at` = NOW(),
                    `editedby` = :edited_by
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':is_active'        => (int)$is_active,
            ':edited_by' => $edited_by,
            ':id'           => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            return "User updated successfully $active.";
        }

        return false;
    }

    function setUserArchived($user_id, $edited_by)
    {
        global $pdo;
        $sql = "UPDATE `connectionyou`.`users` 
                SET 
                    `deleted_at` = NOW(),
                    `updated_at` = NOW(),
                    `editedby` = :edited_by
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':edited_by' => $edited_by,
            ':id'           => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            return "User successfully archived.";
        }

        return false;
    }

    function setUserUnArchived($user_id, $edited_by)
    {
        global $pdo;
        $sql = "UPDATE `connectionyou`.`users` 
                SET 
                    `deleted_at` = NULL,
                    `updated_at` = NOW(),
                    `editedby` = :edited_by
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':edited_by' => $edited_by,
            ':id'           => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            return "User update successful un-archived.";
        }

        return false;
    }

    function setUserLog($request_data, $response_data, $request_method, $response_code, $endpoint, $request_ip)
    {
        global $pdo;
        $sql = "INSERT INTO `connectionyou`.`user_logs`(`request_data`, `response_data`, `request_method`, `response_code`, `endpoint`, `request_ip`) 
                VALUES (:request_data, :response_data, :request_method, :response_code, :endpoint, :request_ip)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':request_data'   => json_encode($request_data),
            ':response_data'  => json_encode($response_data),
            ':request_method' => $request_method,
            ':response_code'  => (int)$response_code,
            ':endpoint'       => $endpoint,
            ':request_ip'     => $request_ip,
        ]);
    }

    function getFailedAttemptCount($endpoint, $request_ip, $minutes)
    {
        global $pdo;
        $sql = "SELECT COUNT(*) AS `attempts`
                FROM `connectionyou`.`user_logs`
                WHERE `endpoint` = :endpoint
                    AND `request_ip` = :request_ip
                    AND `response_code` = 401
                    AND `created_at` >= (NOW() - INTERVAL :minutes MINUTE)";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':endpoint', $endpoint, PDO::PARAM_STR);
        $stmt->bindValue(':request_ip', $request_ip, PDO::PARAM_STR);
        $stmt->bindValue(':minutes', (int)$minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
