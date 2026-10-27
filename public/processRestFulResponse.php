<?php
    require_once __DIR__ . '/../Database.php';
    header('Content-Type: text/plain');

    $clean_post = [];

    foreach ($_POST as $key => $value) {
        $clean_post[trim($key)] = trim($value);
    }

    if (empty($clean_post) || empty($clean_post['ciphertext']) || empty($clean_post['iv'])) {
        echo json_encode(['code' => 400, 'heading' => 'Action is REQUIRED', 'message' => 'Request data can not be null']);
        exit;
    }

    $decryptedArray = decryptData($clean_post['ciphertext'], ['iv']);

    if (!is_array($decryptedArray)) {
        echo json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => 'Request data could not be read.']);
        exit;
    }

    $functionName = $decryptedArray['function'] ?? '';

    if (empty($functionName)) {
        echo json_encode(['code' => 400, 'heading' => 'Action is REQUIRED', 'message' => 'Function can not be null, contact support']);
        exit;
    }

    $edited_by = $_SERVER['REMOTE_ADDR'];

    if ($decryptedArray['function'] === 'createUser') {
        $inArray = checkInArray(['first_name', 'last_name', 'name', 'phone_number', 'email', 'message'], $decryptedArray);

        if (!empty($inArray)) {
            echo $inArray;
            exit;
        }

        $first_name = trim($decryptedArray['first_name'] ?? '');
        $last_name = trim($decryptedArray['last_name'] ?? '');
        $name = trim($decryptedArray['name'] ?? '');
        $phone_number = validateSAMobile($decryptedArray['phone_number'] ?? '') ?: trim($decryptedArray['phone_number'] ?? '');
        $validateSAIDNumber = trim($decryptedArray['id_number_hash'] ?? '');
        $message = trim($decryptedArray['message'] ?? '');
        $email = trim($decryptedArray['email'] ?? '');

        $checks = performGenericChecks($first_name, $last_name, $name, $phone_number, $validateSAIDNumber, $email, $message);

        if (!empty($checks)) {
            echo $checks;
            exit;
        }

        if (count($decryptedArray) < 6) {
            echo json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "More Parameters are required for this endpoint."]);
            exit;            
        }

        $userInsert = setPerformUserInsert($first_name, $last_name, $name, $phone_number, hashIdNumber($validateSAIDNumber), $email, $message, $edited_by);

        if (!$userInsert) {
            echo json_encode(['code' => 304, 'heading' => 'Failed to Create User', 'message' => "User creation failed please try again."]);
            exit;
        }

        echo json_encode(['code' => 201, 'heading' => 'User Created', 'message' => "Successfully created a new user."]);

        unset($decryptedArray);
        exit;
    }

    if ($decryptedArray['function'] === 'updateUserUnArchived') {
        $inArray = checkInArray(['user_id'], $decryptedArray);

        if (!empty($inArray)) {
            echo $inArray;
            exit;
        }

        $user_id = (int)($decryptedArray['user_id'] ?? 0);
        $userArray = getUserById($user_id);

        if (!$userArray) {
            return json_encode(['code' => 304, 'heading' => 'User Not Found', 'message' => "Invalid User Id."]);
        }

        $updatedUser = setUserUnArchived($user_id, $edited_by);

        if (!$updatedUser) {
            echo json_encode(['code' => 304, 'heading' => 'Failed to Update User', 'message' => "User updating failed could not archive, please try again."]);
            exit;
        }

        echo json_encode(['code' => 200, 'heading' => 'User Un-Archived', 'message' => "Successfully un-archived user."]);

        unset($decryptedArray);
        exit;
    }

    if ($decryptedArray['function'] === 'updateUserArchived') {
        $inArray = checkInArray(['user_id'], $decryptedArray);

        if (!empty($inArray)) {
            echo $inArray;
            exit;
        }

        $user_id = (int)($decryptedArray['user_id'] ?? 0);
        $userArray = getUserById($user_id);

        if (!$userArray) {
            return json_encode(['code' => 304, 'heading' => 'User Not Found', 'message' => "Invalid User Id."]);
        }

        $updatedUser = setUserArchived($user_id, $edited_by);

        if (!$updatedUser) {
            echo json_encode(['code' => 304, 'heading' => 'Failed to Update User', 'message' => "User updating failed could not archive, please try again."]);
            exit;
        }

        echo json_encode(['code' => 200, 'heading' => 'User Archived', 'message' => "Successfully archived user."]);

        unset($decryptedArray);
        exit;
    }

    if ($decryptedArray['function'] === 'updateUserStatus') {
        $inArray = checkInArray(['user_id', 'is_active'], $decryptedArray);

        if (!empty($inArray)) {
            echo $inArray;
            exit;
        }

        $is_active = (int)($decryptedArray['is_active'] ?? 0);
        $user_id = (int)($decryptedArray['user_id'] ?? 0);
        $active = 'Activated';

        if(!$is_active) {
            $active = 'Deactivated';
        }

        $userArray = getUserById($user_id);

        if (!$userArray) {
            return json_encode(['code' => 304, 'heading' => 'User Not Found', 'message' => "Invalid User Id."]);
        }

        $updatedUser = setUserStatus($user_id, $is_active, $edited_by);

        if (!$updatedUser) {
            echo json_encode(['code' => 304, 'heading' => 'Failed to Update User', 'message' => "User updating failed please try again."]);
            exit;
        }

        echo json_encode(['code' => 200, 'heading' => 'User Status Updated', 'message' => "Successfully $active user."]);

        unset($decryptedArray);
        exit;
    }

    if ($decryptedArray['function'] === 'updateUser') {
        $inArray = checkInArray(['user_id', 'first_name', 'last_name', 'name', 'phone_number', 'message', 'email'], $decryptedArray);

        if (!empty($inArray)) {
            echo $inArray;
            exit;
        }

        $first_name = trim($decryptedArray['first_name'] ?? '');
        $last_name = trim($decryptedArray['last_name'] ?? '');
        $name = trim($decryptedArray['name'] ?? '');
        $phone_number = validateSAMobile($decryptedArray['phone_number'] ?? '') ?: trim($decryptedArray['phone_number'] ?? '');
        $id_number = trim($decryptedArray['id_number_hash'] ?? '');
        $message = trim($decryptedArray['message'] ?? '');
        $email = trim($decryptedArray['email'] ?? '');
        $user_id = (int)($decryptedArray['user_id'] ?? 0);

        $updateChecks = performUpdateChecks($user_id, $first_name, $last_name, $name, $phone_number, $id_number_hash, $email, $message);

        if (!empty($updateChecks)) {
            echo $updateChecks;
            exit;
        }

        $userArray = getUserById($user_id);
        $id_number_hash = $userArray[0]['id_number_hash'];

        if ($id_number !== '') {
            $id_number_hash = hashIdNumber($id_number);
        }

        $updatedUser = setUpdateRecord($user_id, $first_name, $last_name, $name, $phone_number, $id_number_hash, $email, $message, $edited_by);

        if (!$updatedUser) {
            echo json_encode(['code' => 304, 'heading' => 'Failed to Update User', 'message' => "User updating failed please try again."]);
            exit;
        }

        echo json_encode(['code' => 200, 'heading' => 'User Updated', 'message' => "Successfully updated user."]);

        unset($decryptedArray);
        exit;
    }

    function performGenericChecks($first_name, $last_name, $name, $phone_number, $validateSAIDNumber, $email, $message)
    {
        $nullChecks = performNullChecks($first_name, $last_name, $name, $phone_number, $validateSAIDNumber, $email, $message);

        if (!empty($nullChecks)) {
            return $nullChecks;
        }

        $validations = performValidations($phone_number, $validateSAIDNumber, $email);

        if (!empty($validations)) {
            return $validations;
        }

        $recordExists = performChecksRecordsExists($phone_number, $validateSAIDNumber, $email);

        if (!empty($recordExists)) {
            return $validations;
        }

        return [];
    }

    function checkInArray($array_keys, $arrayToCheckIn)
    {
        foreach ($array_keys as $array_key) {
            if (!array_key_exists($array_key, $arrayToCheckIn)) {
                return json_encode(['code' => 304, 'heading' => 'Missing Parameter ', 'message' => "Parameter ($array_key) is REQUIRED."]);
            }
        }

        return [];
    }

    function performUpdateChecks($user_id, $first_name, $last_name, $name, $phone_number, $validateSAIDNumber, $email, $message)
    {
        $userArray = getUserById($user_id);

        if (!$userArray) {
            return json_encode(['code' => 304, 'heading' => 'User Not Found', 'message' => "Invalid User Id."]);
        }

        $idForNullCheck = $validateSAIDNumber !== '' ? $validateSAIDNumber : $userArray[0]['id_number_hash'];
        $nullChecks = performNullChecks($first_name, $last_name, $name, $phone_number, $idForNullCheck, $email, $message);

        if (!empty($nullChecks)) {
            return $nullChecks;
        }

        $validations = performValidations($phone_number, $validateSAIDNumber, $email);

        if (!empty($validations)) {
            return $validations;
        }

        $emailOwner = getUserByEmail($email, 1);

        if ($emailOwner && (int)$emailOwner[0]['id'] !== (int)$user_id) {
            return json_encode(['code' => 304, 'heading' => 'Email Exists', 'message' => "Email (". $email .") belongs to another entry."]);
        }

        $phoneOwner = getUserByPhoneNumber($phone_number, 1);

        if ($phoneOwner && (int)$phoneOwner[0]['id'] !== (int)$user_id) {
            return json_encode(['code' => 304, 'heading' => 'Mobile Number Exists', 'message' => "Phone Number (". $phone_number .") belongs to another entry."]);
        }

        if ($validateSAIDNumber !== '') {
            $idOwner = getUserByIdNumber(hashIdNumber($validateSAIDNumber), 1);

            if ($idOwner && (int)$idOwner[0]['id'] !== (int)$user_id) {
                return json_encode(['code' => 304, 'heading' => 'ID Number Exists', 'message' => "Provided identity number belongs to another entry."]);
            }
        }

        return [];
    }

    function performNullChecks($first_name, $last_name, $name, $phone_number, $validateSAIDNumber, $email, $message)
    {
        if (empty($first_name) || $first_name == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "First Name is required and can not be NULL."]);
        }

        if (empty($last_name) || $last_name == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "Last Name is required and can not be NULL."]);
        }

        if (empty($name) || $name == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "Full Name is required and can not be NULL."]);
        }

        if (empty($phone_number) || $phone_number == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "Phone Number is required and can not be NULL."]);
        }

        if (empty($validateSAIDNumber) || $validateSAIDNumber == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "ID Number is required and can not be NULL."]);
        }

        if (empty($email) || $email == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "Email is required and can not be NULL."]);
        }

        if (empty($message) || $message == "") {
            return json_encode(['code' => 400, 'heading' => 'Invalid Request', 'message' => "Message is required and can not be NULL."]);
        }

        return [];
    }

    function performValidations($phone_number, $validateSAIDNumber, $email)
    {
        if (!validateSAMobile($phone_number)) {
            return json_encode(['code' => 400, 'heading' => 'Mobile Number is Not Valid', 'message' => "Phone Number (". $phone_number .") is not a valid SA Mobile Number"]);
        }

        if (!validateEmail($email)) {
            return json_encode(['code' => 400, 'heading' => 'Email is Not Valid', 'message' => "Email (". $email .") is not a valid email address"]);
        }

        if (!validateSAIDNumber($validateSAIDNumber)) {
            return json_encode(['code' => 400, 'heading' => 'ID Number is Not Valid', 'message' => "Provided identity number is not a valid SA ID Number"]);
        }

        return [];
    }

    function performChecksRecordsExists($phone_number, $validateSAIDNumber, $email)
    {
        if (getUserByPhoneNumber($phone_number, 1)) {
            return json_encode(['code' => 204, 'heading' => 'Mobile Number Exists', 'message' => "Phone Number (". $phone_number .") is  already registered please try a different."]);
        }

        if (getUserByEmail($email, 1)) {
            return json_encode(['code' => 204, 'heading' => 'Email Exists', 'message' => "Email (". $email .") is already registered please try a different  email address"]);
        }

        if (getUserByIdNumber(hashIdNumber($validateSAIDNumber), 1)) {
            return json_encode(['code' => 204, 'heading' => 'ID Number Exists', 'message' => "Provided identity number is already registered please try a different ID"]);
        }

        return [];
    }
