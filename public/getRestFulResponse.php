<?php
    require_once __DIR__ . '/../Database.php';
    header('Content-Type: application/json');

    $value = isset($_REQUEST['query']) ? $_REQUEST['query'] : '';
    $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
    $limit = 1000;
    $users = "";

    $isQueryRequiredArray = [
                'userByEmail' => NULL,
                'usersByFirstName' => NULL,
                'usersByLastName' => NULL,
                'userByPhoneNumber' => NULL,
                'userByName' => NULL,
                'getActive' => NULL,
                'getAllUsersByDate' => NULL,
                'revealUserField' => NULL,
    ];

    if(empty($type)){
        // not encrypting
        echo json_encode([
            'availableQueryTypes' => [
                'userByEmail' => 'value is required',
                'usersByFirstName' => 'value is required',
                'usersByLastName' => 'value is required',
                'userByName' => 'value is required',
                'userByPhoneNumber' => 'value is required',
                'getActive' => 'value is required',
                'getAllUsersByDate' => 'value is required',
                'getAllUsers' => '',
                'getAllUsersDeleted' => '',
                'getAllUsersNonDeleted' => '',
                'revealUserField' => 'value (encrypted request) and iv are required',
            ]
        ]);
        exit;
    }

    // not empty(): getActive needs to accept '0'
    if (array_key_exists($type, $isQueryRequiredArray) && trim((string)$value) === '') {
        // not encrypting
        echo json_encode("Please note value is required for ($type)!!!");
        exit;
    }

    if ($type === 'getTestQueryTypes') {
        // not encrypting
        echo json_encode(getTestResponseArray());
        exit;
    }

    if ($type === 'revealUserField') {
        echo json_encode(revealUserField($value, isset($_REQUEST['iv']) ? $_REQUEST['iv'] : ''));
        exit;
    }

    switch($type){
        case 'userByEmail':
            $users = getUserByEmail($value, $limit);
            break;
        case 'usersByFirstName':
            $users = getUserByFirstName($value, $limit);
            break;
        case 'usersByLastName':
            $users = getUserByLastName($value, $limit);
            break;
        case 'userByName':
            $users = getUserByName($value, $limit);
            break;
        case 'userByPhoneNumber':
            $users = getUserByPhoneNumber(validateSAMobile($value) ?: $value, $limit);
            break;
        case 'getActive':
            $users = getActiveUsers($value, $limit);
            break;
        case 'getAllUsersByDate':
            $users = getUsersByCreatedAt($value, $limit);
            break;
        case 'getAllUsers':
            $users = getAllUsers($limit);
            break;
        case 'getAllUsersDeleted':
            $users = getAllUsersDeleted($limit);
            break;
        case 'getAllUsersNonDeleted':
            $users = getAllUsersNonDeleted($limit);
            break;
    }

    if (empty($users) || !$users) {
        echo json_encode(['data' => NULL, 'message' => 'Could not find users', 'code' => '400']);
        exit;
    }

    foreach($users as $user) {
        $data[] = ['id' => $user['id'], 'email' => $user['email'],
                        'first_name' => $user['first_name'], 'last_name' => $user['last_name'],
                        'name' => $user['name'], 'phone_number' => maskPhoneNumber($user['phone_number']),
                        'has_id_number' => !empty($user['id_number_encrypted']),
                        'message' => $user['message'], 'is_active' => $user['is_active'],
                        'created_at' => $user['created_at'], 'updated_at' => $user['updated_at'],
                        'editedby' => $user['editedby'], 'id_number_hash' => $user['id_number_hash'],
                        'deleted_at' => $user['deleted_at'],];
    }

    echo json_encode(['data' => ecryptData(array_values($data)), 'message' => '', 'code' => '200']);
    exit;

    function getTestResponseArray()
    {
        $responseArray = ['response' => [
                            'id' => 0, 
                            'email' => '', 
                            'first_name' => '',
                            'last_name' => '',
                            'name' => '',
                            'phone_number' => '',
                            'id_number_hash' => '',
                            'has_id_number' => false,
                            'message' => '',
                            'is_active' => 0,
                            'created_at' => '',
                            'updated_at' => '',
                            'editedby' => '',
                    ]];
        return [
                'userByEmail' => ['request' => ['?query=' => 'email'], $responseArray],
                'userByName' => ['request' => ['?query=' => 'name'], $responseArray],
                'usersByFirstName' => ['request' => ['?query=' => 'first_name'], $responseArray],
                'usersByLastName' => ['request' => ['?query=' => 'last_name'], $responseArray],
                'userByPhoneNumber' => ['request' => ['?query=' => 'phone_number'], $responseArray],
                'getActive' => ['request' => ['?query=' => 'is_active'], $responseArray],
                'getAllUsersByDate' => ['request' => ['?query=' => 'created_at'], $responseArray],
                'getAllUsers' => ['request' => ['?query=' => ''], $responseArray],
                'getAllUsersDeleted' => ['request' => ['?query=' => ''], $responseArray],
                'getAllUsersNonDeleted' => ['request' => ['?query=' => ''], $responseArray],
                'revealUserField' => [
                    'request' => [
                        '?query=' => 'ciphertext of {user_id, field: phone_number|id_number, password, timestamp}',
                        '&iv=' => 'iv',
                    ],
                    ['response' => ['ciphertext of' => ['user_id' => 0, 'field' => '', 'value' => '']]],
                ],
            ];
    }

    function revealUserField($ciphertext, $iv)
    {
        $endpoint = 'revealUserField';
        $request_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $revealableFields = ['phone_number', 'id_number'];
        $maxFailedAttempts = 5;
        $lockoutMinutes = 15;
        $requestLifetimeSeconds = 300;

        if (empty($ciphertext) || empty($iv) || strlen((string)base64_decode($iv, true)) !== 16) {
            return ['data' => NULL, 'message' => 'Request data could not be read.', 'code' => '400'];
        }

        $request = decryptData($ciphertext, $iv);

        if (!is_array($request)) {
            return ['data' => NULL, 'message' => 'Request data could not be read.', 'code' => '400'];
        }

        $user_id = (int)($request['user_id'] ?? 0);
        $field = (string)($request['field'] ?? '');
        $password = (string)($request['password'] ?? '');
        $timestamp = (int)($request['timestamp'] ?? 0);

        if (!in_array($field, $revealableFields, true)) {
            return ['data' => NULL, 'message' => 'That field can not be shown.', 'code' => '400'];
        }

        // stops a logged GET url from being replayed later
        if (abs(time() - $timestamp) > $requestLifetimeSeconds) {
            return ['data' => NULL, 'message' => 'Request has expired. Check your device clock and try again.', 'code' => '400'];
        }

        if (!isRevealPasswordConfigured()) {
            return ['data' => NULL, 'message' => 'Showing protected details is not set up on the server (REVEAL_PASSWORD).', 'code' => '503'];
        }

        if (getRevealFailedAttempts($endpoint, $request_ip, $lockoutMinutes) >= $maxFailedAttempts) {
            logRevealAttempt($endpoint, $user_id, $field, '429', $request_ip);
            return ['data' => NULL, 'message' => "Too many incorrect passwords. Try again in $lockoutMinutes minutes.", 'code' => '429'];
        }

        if (!verifyRevealPassword($password)) {
            logRevealAttempt($endpoint, $user_id, $field, '401', $request_ip);
            return ['data' => NULL, 'message' => 'Incorrect password.', 'code' => '401'];
        }

        $userArray = getUserById($user_id);

        if (!$userArray) {
            return ['data' => NULL, 'message' => 'Invalid User Id.', 'code' => '404'];
        }

        $user = $userArray[0];
        $plaintext = $user['phone_number'];

        if ($field === 'id_number') {
            if (empty($user['id_number_encrypted'])) {
                return ['data' => NULL, 'message' => 'No ID number on file that can be shown for this entry.', 'code' => '404'];
            }

            $plaintext = decryptIdNumber($user['id_number_encrypted']);

            if ($plaintext === false || $plaintext === NULL) {
                return ['data' => NULL, 'message' => 'ID number could not be decrypted. Check ENCRYPTION_KEY.', 'code' => '500'];
            }
        }

        logRevealAttempt($endpoint, $user_id, $field, '200', $request_ip);

        return ['data' => ecryptData(['user_id' => $user_id, 'field' => $field, 'value' => $plaintext]), 'message' => '', 'code' => '200'];
    }

    // audit trail + rate limiting, never stores the password or the revealed value
    function logRevealAttempt($endpoint, $user_id, $field, $code, $request_ip)
    {
        try {
            setUserLog(
                json_encode(['user_id' => $user_id, 'field' => $field]),
                json_encode(['code' => (int)$code]),
                'GET',
                $code,
                $endpoint,
                $request_ip,
                $request_ip
            );
        } catch (\PDOException $e) {
            error_log('Could not write reveal log: ' . $e->getMessage());
        }
    }

    function getRevealFailedAttempts($endpoint, $request_ip, $minutes)
    {
        try {
            return getFailedAttemptsByIp($endpoint, $request_ip, $minutes);
        } catch (\PDOException $e) {
            error_log('Could not read reveal log: ' . $e->getMessage());
            return 0;
        }
    }


