<?php
    require __DIR__ . '/../Database.php';
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

    switch($type){
        case 'userByEmail':
            $users = getUserByEmail(escapeString($value), $limit);
            break;
        case 'usersByFirstName':
            $users = getUserByFirstName(escapeString($value), $limit);
            break;
        case 'usersByLastName':
            $users = getUserByLastName(escapeString($value), $limit);
            break;
        case 'userByName':
            $users = getUserByName(escapeString($value), $limit);
            break;
        case 'userByPhoneNumber':
            $users = getUserByPhoneNumber(escapeString($value), $limit);
            break;
        case 'getActive':
            $users = getActiveUsers(escapeString($value), $limit);
            break;
        case 'getAllUsersByDate':
            $users = getUsersByCreatedAt(escapeString($value), $limit);
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
                        'name' => $user['name'], 'phone_number' => $user['phone_number'],
                        'message' => $user['message'], 'is_active' => $user['is_active'],
                        'created_at' => $user['created_at'], 'updated_at' => $user['updated_at'],
                        'editedby' => $user['editedby'], 'id_number_hash' => $user['id_number_hash'],];
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

    function escapeString($string)
    {
        $encoded = htmlspecialchars($string ?? '', ENT_COMPAT, 'iso-8859-1');
        $return = mysqli_real_escape_string($dbconntouse, $encoded);

        return $return;
    }

