<?php
    namespace Tests\Feature;

    use PHPUnit\Framework\Attributes\DataProvider;
    use Tests\Support\ApiTestCase;

    /**
     * public/getRestFulResponse.php – the list and search types.
     * revealUserField has its own test class.
     */
    final class GetRestFulResponseTest extends ApiTestCase
    {
        // ---------- discovery ----------

        public function testNoTypeListsAvailableQueryTypesUnencrypted(): void
        {
            $response = $this->get(['type' => '']);

            $this->assertSame(200, $response['status']);
            $this->assertSame([
                'userByEmail', 'usersByFirstName', 'usersByLastName', 'userByName', 'userByPhoneNumber',
                'getActive', 'getAllUsersByDate', 'getAllUsers', 'getAllUsersDeleted', 'getAllUsersNonDeleted',
                'revealUserField',
            ], array_keys($response['json']['availableQueryTypes']));
        }

        public function testGetTestQueryTypesDescribesEveryType(): void
        {
            $response = $this->get(['type' => 'getTestQueryTypes']);
            $types = $response['json'];

            $this->assertArrayHasKey('revealUserField', $types);
            $this->assertArrayHasKey('getAllUsers', $types);
            $this->assertArrayHasKey('has_id_number', $types['userByEmail'][0]['response']);
        }

        public static function typesThatNeedAQuery(): array
        {
            return [
                ['userByEmail'], ['usersByFirstName'], ['usersByLastName'], ['userByName'],
                ['userByPhoneNumber'], ['getActive'], ['getAllUsersByDate'], ['revealUserField'],
            ];
        }

        /** @dataProvider typesThatNeedAQuery */
        #[DataProvider('typesThatNeedAQuery')]
        public function testTypesThatNeedAQueryRejectAnEmptyOne(string $type): void
        {
            $response = $this->get(['type' => $type]);

            $this->assertSame("Please note value is required for ($type)!!!", $response['json']);
        }

        public function testWhitespaceOnlyQueryCountsAsEmpty(): void
        {
            $this->assertSame('Please note value is required for (userByEmail)!!!', $this->get(['type' => 'userByEmail', 'query' => '   '])['json']);
        }

        public function testUnknownTypeFindsNothing(): void
        {
            $this->insertUser();

            $response = $this->get(['type' => 'dropUsers']);

            $this->assertSame(['data' => null, 'message' => 'Could not find users', 'code' => '400'], $response['json']);
        }

        // ---------- response shape and privacy ----------

        public function testListResponseIsEncrypted(): void
        {
            $this->insertUser(['name' => 'Ayesha Patel', 'email' => 'ayesha@example.com', 'phone_number' => '+27731239876']);

            $response = $this->get(['type' => 'getAllUsers']);

            $this->assertSame('200', $response['json']['code']);
            $this->assertSame(['ciphertext', 'iv'], array_keys($response['json']['data']));
            $this->assertStringNotContainsString('Ayesha', $response['raw']);
            $this->assertStringNotContainsString('ayesha@example.com', $response['raw']);
        }

        public function testListMasksPhoneNumbersAndNeverReturnsTheIdNumber(): void
        {
            $this->insertUser(['phone_number' => '+27781234509', 'id_number' => '8001015009087']);

            $users = $this->getUsers('getAllUsers');

            $this->assertCount(1, $users);
            $this->assertSame('078*****09', $users[0]['phone_number']);
            $this->assertTrue($users[0]['has_id_number']);
            $this->assertArrayNotHasKey('id_number_encrypted', $users[0]);
            $this->assertArrayNotHasKey('id_number', $users[0]);

            $decryptedJson = json_encode($users);
            $this->assertStringNotContainsString('781234509', $decryptedJson);
            $this->assertStringNotContainsString('8001015009087', $decryptedJson);
        }

        public function testHasIdNumberIsFalseWhenNoEncryptedIdIsStored(): void
        {
            $this->insertUser(['id_number' => null]);

            $users = $this->getUsers('getAllUsers');

            $this->assertFalse($users[0]['has_id_number']);
        }

        public function testRowsHaveTheDocumentedFields(): void
        {
            $this->insertUser();

            $users = $this->getUsers('getAllUsers');

            $this->assertSame([
                'id', 'email', 'first_name', 'last_name', 'name', 'phone_number', 'has_id_number', 'message',
                'is_active', 'created_at', 'updated_at', 'editedby', 'id_number_hash', 'deleted_at',
            ], array_keys($users[0]));
        }

        // ---------- each search type ----------

        public function testUserByEmail(): void
        {
            $id = $this->insertUser(['email' => 'pieter@example.org']);
            $this->insertUser(['email' => 'someone@example.org']);

            $users = $this->getUsers('userByEmail', 'pieter@example.org');

            $this->assertSame([$id], array_column($users, 'id'));
        }

        public function testUsersByFirstName(): void
        {
            $first = $this->insertUser(['first_name' => 'Sipho']);
            $second = $this->insertUser(['first_name' => 'Sipho']);
            $this->insertUser(['first_name' => 'Lerato']);

            $users = $this->getUsers('usersByFirstName', 'Sipho');

            $this->assertEqualsCanonicalizing([$first, $second], array_column($users, 'id'));
        }

        public function testUsersByLastName(): void
        {
            $id = $this->insertUser(['last_name' => 'Dlamini']);
            $this->insertUser(['last_name' => 'Mokoena']);

            $users = $this->getUsers('usersByLastName', 'Dlamini');

            $this->assertSame([$id], array_column($users, 'id'));
        }

        public function testUserByName(): void
        {
            $id = $this->insertUser(['name' => 'Johan Botha']);
            $this->insertUser(['name' => 'Johan Bothma']);

            $users = $this->getUsers('userByName', 'Johan Botha');

            $this->assertSame([$id], array_column($users, 'id'));
        }

        public static function phoneSearchFormats(): array
        {
            return [
                'international' => ['+27781234509'],
                'local'         => ['0781234509'],
                'local spaced'  => ['078 123 4509'],
            ];
        }

        /** @dataProvider phoneSearchFormats */
        #[DataProvider('phoneSearchFormats')]
        public function testUserByPhoneNumberAcceptsAnyFormat(string $query): void
        {
            $id = $this->insertUser(['phone_number' => '+27781234509']);
            $this->insertUser(['phone_number' => '+27781234500']);

            $users = $this->getUsers('userByPhoneNumber', $query);

            $this->assertSame([$id], array_column($users, 'id'));
        }

        public function testUserByPhoneNumberFindsEntriesCreatedThroughTheForm(): void
        {
            $this->assertSame(201, $this->post($this->userPayload(['phone_number' => '+27781234509']))['json']['code']);

            $users = $this->getUsers('userByPhoneNumber', '+27781234509');

            $this->assertCount(1, $users);
        }

        public function testGetActive(): void
        {
            $active = $this->insertUser(['is_active' => 1]);
            $inactive = $this->insertUser(['is_active' => 0]);

            $this->assertSame([$active], array_column($this->getUsers('getActive', '1'), 'id'));
            $this->assertSame([$inactive], array_column($this->getUsers('getActive', '0'), 'id'));
        }

        public function testGetAllUsersByDate(): void
        {
            $today = $this->insertUser(['created_at' => '2026-11-18 09:15:00']);
            $this->insertUser(['created_at' => '2026-11-17 23:59:59']);

            $users = $this->getUsers('getAllUsersByDate', '2026-11-18');

            $this->assertSame([$today], array_column($users, 'id'));
        }

        public function testGetAllUsersIncludesArchived(): void
        {
            $open = $this->insertUser();
            $archived = $this->insertUser(['deleted_at' => '2026-11-01 10:00:00']);

            $users = $this->getUsers('getAllUsers');

            $this->assertEqualsCanonicalizing([$open, $archived], array_column($users, 'id'));
        }

        public function testGetAllUsersDeletedAndNonDeleted(): void
        {
            $open = $this->insertUser();
            $archived = $this->insertUser(['deleted_at' => '2026-11-01 10:00:00']);

            $this->assertSame([$archived], array_column($this->getUsers('getAllUsersDeleted'), 'id'));
            $this->assertSame([$open], array_column($this->getUsers('getAllUsersNonDeleted'), 'id'));
        }

        // ---------- not found / hostile input ----------

        public function testNoMatchReturnsCode400WithNullData(): void
        {
            $this->insertUser(['email' => 'real@example.com']);

            $response = $this->get(['type' => 'userByEmail', 'query' => 'nobody@example.com']);

            $this->assertSame(['data' => null, 'message' => 'Could not find users', 'code' => '400'], $response['json']);
        }

        public function testEmptyTableReturnsCode400(): void
        {
            $response = $this->get(['type' => 'getAllUsers']);

            $this->assertSame('400', $response['json']['code']);
        }

        public static function injectionAttempts(): array
        {
            return [
                ['userByEmail', "' OR '1'='1"],
                ['userByName', "x' OR 1=1 -- "],
                ['getAllUsersByDate', "2026-11-18' OR '1'='1"],
                ['getActive', '0 OR 1=1'],
            ];
        }

        /** @dataProvider injectionAttempts */
        #[DataProvider('injectionAttempts')]
        public function testSearchValuesAreBoundNotConcatenated(string $type, string $query): void
        {
            // dated in the past so only an injected OR could make them match
            $this->insertUser(['created_at' => '2025-01-01 08:00:00']);
            $this->insertUser(['created_at' => '2025-01-01 08:00:00']);

            $this->assertSame([], $this->getUsers($type, $query));
            $this->assertSame(2, $this->userCount());
        }
    }
