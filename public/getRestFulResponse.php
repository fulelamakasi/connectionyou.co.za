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
                'revealUser' => NULL,
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
                'revealUser' => 'value (user id) is required, field=phone|id, X-Reveal-Password header',
            ]
        ]);
        exit;
    }

    if (array_key_exists($type, $isQueryRequiredArray) && empty($value)) {
        // not encrypting
        echo json_encode("Please note value is required for ($type)!!!");
        exit;
    }

    if ($type === 'getTestQueryTypes') {
        // not encrypting
        echo json_encode(getTestResponseArray());
        exit;
    }

    if ($type === 'revealUser') {
        revealUserField((int)$value, isset($_REQUEST['field']) ? trim($_REQUEST['field']) : '');
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
            $users = getUserByPhoneNumber($value, $limit);
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
                        'message' => $user['message'], 'is_active' => $user['is_active'],
                        'created_at' => $user['created_at'], 'updated_at' => $user['updated_at'],
                        'editedby' => $user['editedby'], 'id_number_hash' => $user['id_number_hash'],
                        'deleted_at' => $user['deleted_at'],
                        'id_number_available' => !empty($user['id_number_encrypted']),];
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
            ];
    }

    function revealUserField($user_id, $field)
    {
        $endpoint = 'revealUser';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $maxAttempts = 5;
        $windowMinutes = 15;
        $logRequest = ['user_id' => $user_id, 'field' => $field];

        if (!in_array($field, ['phone', 'id'], true)) {
            echo json_encode(['data' => NULL, 'message' => 'Field must be phone or id.', 'code' => '400']);
            return;
        }

        $expected = ($_ENV['REVEAL_PASSWORD'] ?: '');

        if ($expected === '') {
            echo json_encode(['data' => NULL, 'message' => 'REVEAL_PASSWORD is not set on the server.', 'code' => '503']);
            return;
        }

        $failures = getFailedAttemptCount($endpoint, $ip, $windowMinutes);

        if ($failures >= $maxAttempts) {
            echo json_encode(['data' => NULL, 'message' => "Too many incorrect passwords. Try again in $windowMinutes minutes.", 'code' => '429']);
            return;
        }

        $provided = rawurldecode(($_SERVER['HTTP_X_REVEAL_PASSWORD'] ?? ''));

        if ($provided === '' || !hash_equals($expected, $provided)) {
            $remaining = max(0, $maxAttempts - $failures - 1);
            $response = ['data' => NULL, 'message' => 'Incorrect password.', 'code' => '401', 'remaining' => $remaining];
            setUserLog($logRequest, $response, 'GET', 401, $endpoint, $ip);
            echo json_encode($response);
            return;
        }

        $userArray = getUserById($user_id);

        if (!$userArray) {
            echo json_encode(['data' => NULL, 'message' => 'Invalid User Id.', 'code' => '404']);
            return;
        }

        $user = $userArray[0];
        $value = $field === 'phone' ? $user['phone_number'] : decryptAtRest($user['id_number_encrypted'] ?? null);

        if ($value === null || $value === '') {
            setUserLog($logRequest, ['code' => 404], 'GET', 404, $endpoint, $ip);
            echo json_encode(['data' => NULL, 'message' => 'No readable ID number on file for this entry. Edit the entry and re-enter the ID to store it.', 'code' => '404']);
            return;
        }

        setUserLog($logRequest, ['code' => 200], 'GET', 200, $endpoint, $ip);

        echo json_encode(['data' => ecryptData(['user_id' => $user_id, 'field' => $field, 'value' => $value]), 'message' => '', 'code' => '200']);
    }
