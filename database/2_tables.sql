use connectionyou;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `name` VARCHAR(500) NOT NULL,
    `phone_number` VARCHAR(255) NOT NULL,
    `id_number_hash` VARCHAR(64) NOT NULL,
    `id_number_encrypted` VARCHAR(255) DEFAULT NULL,
    `message` LONGTEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT '1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP DEFAULT NULL,
    `editedby` VARCHAR(100) NOT NULL DEFAULT '',
    UNIQUE (`email`),
    UNIQUE (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_logs` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_data` LONGTEXT NOT NULL,
    `response_data` LONGTEXT NOT NULL,
    `request_method` VARCHAR(500) NOT NULL,
    `response_code` INT(10) UNSIGNED NOT NULL DEFAULT '200',
    `endpoint` VARCHAR(500) NOT NULL,
    `request_ip` VARCHAR(500) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP DEFAULT NULL,
    `editedby` VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
