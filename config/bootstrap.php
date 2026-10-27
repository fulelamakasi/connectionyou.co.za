<?php
    define('BASE_PATH', dirname(__DIR__));

    function loadEnv($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        return true;
    }

    loadEnv(__DIR__ . '/.env');

    function ecryptData($data)
    {
        $plaintext = json_encode($data);
        $secretKey = getenv('ENCRYPTION_KEY'); 
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encryptedRaw = openssl_encrypt($plaintext, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $iv);

        return [
            'ciphertext' => base64_encode($encryptedRaw),
            'iv' => base64_encode($iv)
        ];
    }

    function decryptData($ciphertext, $iv)
    {
        $secretKey = getenv('ENCRYPTION_KEY'); 
        $ciphertextRaw = base64_decode($ciphertext);
        $ivDecoded = base64_decode($iv);
        $decryptedRaw = openssl_decrypt($ciphertextRaw, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $ivDecoded);

        return json_decode($decryptedRaw, true);
    }

    // use generic validator functions 

    function validateSAMobile($phone_number)
    {
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', trim($phone_number));
        $pattern = '/^(?:\+?27|0)([1-8][0-9]\d{7})$/';

        if (preg_match($pattern, $cleaned, $matches)) {
            return '+27 ' . $matches[1];
        }

        return false;
    }

    function validateEmail($email)
    {   
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }

    function validateSAIDNumber($id_number_hash) 
    {
        $id_number_hash = trim($id_number_hash);

        if (!preg_match('/^\d{13}$/', $id_number_hash)) {
            return false;
        }

        $year  = substr($id_number_hash, 0, 2);
        $month = substr($id_number_hash, 2, 2);
        $day   = substr($id_number_hash, 4, 2);

        $fullYear = (int)$year <= 29 ? '20' . $year : '19' . $year;

        if (!checkdate((int)$month, (int)$day, (int)$fullYear)) {
            return false;
        }

        $citizenship = (int)$id_number_hash[10];
        if ($citizenship !== 0 && $citizenship !== 1) {
            return false;
        }

        $sum = 0;
        $length = strlen($id_number_hash);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = (int)$id_number_hash[$i];

            if (($i % 2) !== ($length % 2)) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10 === 0);
    }

    function hashIdNumber($id_number)
    {
        $appKey = getenv('APP_KEY') ?: '';

        if (strpos($appKey, 'base64:') === 0) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hmac('sha256', trim($id_number), $appKey);
    }

    // todo nice to have when decrypting idNumber add endpoint
    function decryptWithPassword($encryptedData, $userPassword) {
        $salt = 'your_app_specific_static_salt_string'; 
        $secretKey = hash_pbkdf2("sha256", $userPassword, $salt, 10000, 32, true);

        $decodedData = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        
        $iv = substr($decodedData, 0, $ivLength);
        $ciphertextRaw = substr($decodedData, $ivLength);

        $decryptedRaw = openssl_decrypt(
            $ciphertextRaw, 
            'aes-256-cbc', 
            $secretKey, 
            OPENSSL_RAW_DATA, 
            $iv
        );

        if ($decryptedRaw === false) {
            return null;
        }

        return json_decode($decryptedRaw, true);
    }

    function decryptIdNumber($encryptedData) {
        $secretKey = getenv('ENCRYPTION_KEY');
        
        if (!$secretKey) {
            return "ENCRYPTION_KEY is not set in the environment.";
        }

        $decodedData = base64_decode($encryptedData);

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decodedData, 0, $ivLength);
        $ciphertextRaw = substr($decodedData, $ivLength);

        $decryptedRaw = openssl_decrypt(
            $ciphertextRaw, 
            'aes-256-cbc', 
            $secretKey, 
            OPENSSL_RAW_DATA, 
            $iv
        );

        if ($decryptedRaw === false) {
            return false;
        }

        return json_decode($decryptedRaw, true);
    }
