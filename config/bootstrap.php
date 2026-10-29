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

    loadEnv(__DIR__ . '/../.env');

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

    function validateSAIDNumber($idNumber)
    {
        $idNumber = trim((string)$idNumber);

        if (!preg_match('/^\d{13}$/', $idNumber)) {
            return false;
        }

        $yy    = (int)substr($idNumber, 0, 2);
        $month = (int)substr($idNumber, 2, 2);
        $day   = (int)substr($idNumber, 4, 2);

        // Resolve century: if YY is after the current year's YY, it's 1900s
        $currentYY = (int)date('y');
        $fullYear  = ($yy <= $currentYY) ? 2000 + $yy : 1900 + $yy;

        if (!checkdate($month, $day, $fullYear)) {
            return false;
        }

        // Reject future birthdates
        if (mktime(0, 0, 0, $month, $day, $fullYear) > time()) {
            return false;
        }

        // Citizenship: 0 = SA citizen, 1 = permanent resident, 2 = refugee
        $citizenship = (int)$idNumber[10];
        if (!in_array($citizenship, [0, 1, 2], true)) {
            return false;
        }

        // Luhn checksum — double every second digit from the right,
        // excluding the check digit (i.e. odd indices for a 13-digit number)
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $digit = (int)$idNumber[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    function hashIdNumber($id_number)
    {
        $appKey = getenv('APP_KEY') ?: '';

        if (strpos($appKey, 'base64:') === 0) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hmac('sha256', trim($id_number), $appKey);
    }

    function getAtRestKey()
    {
        $appKey = getenv('APP_KEY') ?: '';

        if (strpos($appKey, 'base64:') === 0) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        if ($appKey === '' || $appKey === false) {
            return false;
        }

        return hash_hmac('sha256', 'connectionyou:at-rest:v1', $appKey, true);
    }

    function encryptAtRest($plaintext)
    {
        $key = getAtRestKey();

        if ($key === false || $plaintext === null || $plaintext === '') {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt((string)$plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            return null;
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    function decryptAtRest($encoded)
    {
        $key = getAtRestKey();

        if ($key === false || empty($encoded)) {
            return null;
        }

        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) <= 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }

    function maskPhoneNumber($phone_number)
    {
        $digits = preg_replace('/\D+/', '', (string)$phone_number);

        if (strpos($digits, '27') === 0 && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }

        if (strlen($digits) < 6) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, 3) . str_repeat('*', strlen($digits) - 5) . substr($digits, -2);
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
