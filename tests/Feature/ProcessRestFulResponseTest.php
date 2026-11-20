<?php
    namespace Tests\Feature;

    use PHPUnit\Framework\Attributes\DataProvider;
    use Tests\Support\ApiTestCase;

    /**
     * public/processRestFulResponse.php – every POST function, sent the way
     * postRequests.js sends it (AES envelope as ciphertext + iv form fields).
     */
    final class ProcessRestFulResponseTest extends ApiTestCase
    {
        // ---------- envelope ----------

        public function testEmptyPostIsRejected(): void
        {
            $response = $this->postForm([]);

            $this->assertSame(['code' => 400, 'heading' => 'Action is REQUIRED', 'message' => 'Request data can not be null'], $response['json']);
        }

        public function testMissingIvIsRejected(): void
        {
            $envelope = ecryptData($this->userPayload());

            $this->assertSame(400, $this->postForm(['ciphertext' => $envelope['ciphertext']])['json']['code']);
            $this->assertSame(0, $this->userCount());
        }

        public function testUnreadableCiphertextIsRejected(): void
        {
            $response = $this->postForm(['ciphertext' => base64_encode(random_bytes(64)), 'iv' => base64_encode(random_bytes(16))]);

            $this->assertSame(['code' => 400, 'heading' => 'Invalid Request', 'message' => 'Request data could not be read.'], $response['json']);
        }

        public function testPlaintextFormFieldsAreIgnored(): void
        {
            // someone posting the fields directly, without the encrypted envelope
            $response = $this->postForm($this->userPayload() + ['ciphertext' => 'x', 'iv' => 'y']);

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame(0, $this->userCount());
        }

        public function testMissingFunctionIsRejected(): void
        {
            $payload = $this->userPayload();
            unset($payload['function']);

            $response = $this->post($payload);

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('Function can not be null, contact support', $response['json']['message']);
        }

        public function testUnknownFunctionIsRejected(): void
        {
            $response = $this->post(['function' => 'deleteEverything']);

            $this->assertSame(['code' => 400, 'heading' => 'Invalid Request', 'message' => 'Function (deleteEverything) is not supported.'], $response['json']);
        }

        // ---------- createUser ----------

        public function testCreateUserSavesTheEnquiry(): void
        {
            $response = $this->post($this->userPayload());

            $this->assertSame(['code' => 201, 'heading' => 'User Created', 'message' => 'Successfully created a new user.'], $response['json']);

            $user = $this->findUserByEmail('thandiwe@example.com');

            $this->assertNotNull($user);
            $this->assertSame('Thandiwe', $user['first_name']);
            $this->assertSame('Nkosi', $user['last_name']);
            $this->assertSame('Thandiwe Nkosi', $user['name']);
            $this->assertSame('I would like a quote.', $user['message']);
            $this->assertSame(1, (int)$user['is_active']);
            $this->assertNull($user['deleted_at']);
            $this->assertSame('127.0.0.1', $user['editedby']);
        }

        public function testCreateUserStoresThePhoneInOneFormat(): void
        {
            $this->post($this->userPayload(['phone_number' => '078 123 4509']));

            $this->assertSame('+27781234509', $this->findUserByEmail('thandiwe@example.com')['phone_number']);
        }

        public function testCreateUserStoresTheIdAsHashAndEncryptedCopyNeverPlaintext(): void
        {
            $this->post($this->userPayload(['id_number_hash' => '8001015009087']));

            $user = $this->findUserByEmail('thandiwe@example.com');

            $this->assertSame(hashIdNumber('8001015009087'), $user['id_number_hash']);
            $this->assertSame('8001015009087', decryptIdNumber($user['id_number_encrypted']));
            $this->assertStringNotContainsString('8001015009087', json_encode($user));
        }

        public function testCreateUserWithoutAnIdNumber(): void
        {
            $response = $this->post($this->userPayload(['id_number_hash' => '']));

            $this->assertSame(201, $response['json']['code']);
            $this->assertNull($this->findUserByEmail('thandiwe@example.com')['id_number_encrypted']);
        }

        public function testTrimsWhitespace(): void
        {
            $this->post($this->userPayload(['first_name' => '  Thandiwe  ', 'email' => ' thandiwe@example.com ']));

            $user = $this->findUserByEmail('thandiwe@example.com');

            $this->assertNotNull($user);
            $this->assertSame('Thandiwe', $user['first_name']);
        }

        public static function requiredCreateParameters(): array
        {
            return [['first_name'], ['last_name'], ['name'], ['phone_number'], ['email'], ['message']];
        }

        /** @dataProvider requiredCreateParameters */
        #[DataProvider('requiredCreateParameters')]
        public function testCreateUserNeedsEveryParameter(string $parameter): void
        {
            $payload = $this->userPayload();
            unset($payload[$parameter]);

            $response = $this->post($payload);

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame("Parameter ($parameter) is REQUIRED.", $response['json']['message']);
            $this->assertSame(0, $this->userCount());
        }

        public static function blankCreateFields(): array
        {
            return [
                ['first_name', 'First Name is required and can not be NULL.'],
                ['last_name', 'Last Name is required and can not be NULL.'],
                ['name', 'Full Name is required and can not be NULL.'],
                ['phone_number', 'Phone Number is required and can not be NULL.'],
                ['email', 'Email is required and can not be NULL.'],
                ['message', 'Message is required and can not be NULL.'],
            ];
        }

        /** @dataProvider blankCreateFields */
        #[DataProvider('blankCreateFields')]
        public function testCreateUserRejectsBlankFields(string $field, string $message): void
        {
            $response = $this->post($this->userPayload([$field => '   ']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame($message, $response['json']['message']);
            $this->assertSame(0, $this->userCount());
        }

        public function testCreateUserRejectsAnInvalidPhoneNumber(): void
        {
            $response = $this->post($this->userPayload(['phone_number' => '0981234509']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('Mobile Number is Not Valid', $response['json']['heading']);
            $this->assertSame(0, $this->userCount());
        }

        public function testCreateUserRejectsAnInvalidEmail(): void
        {
            $response = $this->post($this->userPayload(['email' => 'thandiwe@']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('Email is Not Valid', $response['json']['heading']);
            $this->assertSame(0, $this->userCount());
        }

        public function testCreateUserRejectsAnInvalidIdNumber(): void
        {
            $response = $this->post($this->userPayload(['id_number_hash' => '8001015009088']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('ID Number is Not Valid', $response['json']['heading']);
            $this->assertStringNotContainsString('8001015009088', $response['raw'], 'The ID number is never echoed back.');
            $this->assertSame(0, $this->userCount());
        }

        public function testCreateUserRejectsADuplicatePhoneNumberInAnyFormat(): void
        {
            $this->insertUser(['phone_number' => '+27781234509']);

            $response = $this->post($this->userPayload(['phone_number' => '0781234509', 'id_number_hash' => '']));

            $this->assertSame(204, $response['json']['code']);
            $this->assertSame('Mobile Number Exists', $response['json']['heading']);
            $this->assertSame(1, $this->userCount());
        }

        public function testCreateUserRejectsADuplicateEmail(): void
        {
            $this->insertUser(['email' => 'thandiwe@example.com']);

            $response = $this->post($this->userPayload(['id_number_hash' => '']));

            $this->assertSame(204, $response['json']['code']);
            $this->assertSame('Email Exists', $response['json']['heading']);
            $this->assertSame(1, $this->userCount());
        }

        public function testCreateUserRejectsADuplicateIdNumber(): void
        {
            $this->insertUser(['id_number' => '8001015009087']);

            $response = $this->post($this->userPayload(['id_number_hash' => '8001015009087']));

            $this->assertSame(204, $response['json']['code']);
            $this->assertSame('ID Number Exists', $response['json']['heading']);
            $this->assertSame(1, $this->userCount());
        }

        public function testTwoEnquiriesWithoutIdNumbersAreAllowed(): void
        {
            $this->assertSame(201, $this->post($this->userPayload(['id_number_hash' => '']))['json']['code']);
            $this->assertSame(201, $this->post($this->userPayload([
                'id_number_hash' => '',
                'email'          => 'second@example.com',
                'phone_number'   => '+27781234500',
            ]))['json']['code']);
        }

        public function testHtmlInFieldsIsStoredAsTyped(): void
        {
            // escaping is the frontend's job (escapeHtml in ui.js); the API must not mangle data
            $this->post($this->userPayload(['message' => '<script>alert(1)</script> & "quotes"']));

            $this->assertSame('<script>alert(1)</script> & "quotes"', $this->findUserByEmail('thandiwe@example.com')['message']);
        }

        // ---------- updateUser ----------

        private function updatePayload(int $userId, array $overrides = []): array
        {
            return array_merge($this->userPayload([
                'function'       => 'updateUser',
                'user_id'        => $userId,
                'first_name'     => 'Thandi',
                'last_name'      => 'Nkosi-Mahlangu',
                'name'           => 'Thandi Nkosi-Mahlangu',
                'email'          => 'thandi@example.com',
                'phone_number'   => '+27821112222',
                'id_number_hash' => '',
                'message'        => 'Updated message.',
            ]), $overrides);
        }

        public function testUpdateUserSavesTheChanges(): void
        {
            $id = $this->insertUser(['id_number' => '8001015009087']);

            $response = $this->post($this->updatePayload($id));

            $this->assertSame(['code' => 200, 'heading' => 'User Updated', 'message' => 'Successfully updated user.'], $response['json']);

            $user = $this->findUser($id);
            $this->assertSame('Thandi Nkosi-Mahlangu', $user['name']);
            $this->assertSame('thandi@example.com', $user['email']);
            $this->assertSame('+27821112222', $user['phone_number']);
            $this->assertSame('Updated message.', $user['message']);
            $this->assertNotNull($user['updated_at']);
            $this->assertSame('127.0.0.1', $user['editedby']);
        }

        public function testUpdateUserWithABlankPhoneKeepsTheNumberOnFile(): void
        {
            // the entries page only has the masked number unless it was revealed
            $id = $this->insertUser(['phone_number' => '+27781234509']);

            $response = $this->post($this->updatePayload($id, ['phone_number' => '']));

            $this->assertSame(200, $response['json']['code']);
            $this->assertSame('+27781234509', $this->findUser($id)['phone_number']);
        }

        public function testUpdateUserWithABlankIdKeepsTheIdOnFile(): void
        {
            $id = $this->insertUser(['id_number' => '8001015009087']);
            $before = $this->findUser($id);

            $this->post($this->updatePayload($id, ['id_number_hash' => '']));
            $after = $this->findUser($id);

            $this->assertSame($before['id_number_hash'], $after['id_number_hash']);
            $this->assertSame($before['id_number_encrypted'], $after['id_number_encrypted']);
        }

        public function testUpdateUserWithANewIdReplacesHashAndEncryptedCopy(): void
        {
            $id = $this->insertUser(['id_number' => '8001015009087']);
            $newId = self::makeIdNumber('900202');

            $this->assertSame(200, $this->post($this->updatePayload($id, ['id_number_hash' => $newId]))['json']['code']);

            $user = $this->findUser($id);
            $this->assertSame(hashIdNumber($newId), $user['id_number_hash']);
            $this->assertSame($newId, decryptIdNumber($user['id_number_encrypted']));

            $revealed = $this->reveal($id, 'id_number', self::REVEAL_PASSWORD);
            $this->assertSame($newId, $revealed['plain']['value']);
        }

        public function testUpdateUserCanKeepItsOwnEmailPhoneAndId(): void
        {
            $id = $this->insertUser(['email' => 'thandi@example.com', 'phone_number' => '+27821112222', 'id_number' => '8001015009087']);

            $response = $this->post($this->updatePayload($id, ['id_number_hash' => '8001015009087']));

            $this->assertSame(200, $response['json']['code']);
        }

        public function testSavingUnchangedDetailsTwiceInTheSameSecondSucceeds(): void
        {
            $id = $this->insertUser();

            $this->assertSame(200, $this->post($this->updatePayload($id))['json']['code']);
            $this->assertSame(200, $this->post($this->updatePayload($id))['json']['code']);
        }

        public function testUpdateUserRejectsAnEmailThatBelongsToSomeoneElse(): void
        {
            $this->insertUser(['email' => 'taken@example.com']);
            $id = $this->insertUser();

            $response = $this->post($this->updatePayload($id, ['email' => 'taken@example.com']));

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('Email Exists', $response['json']['heading']);
            $this->assertNotSame('taken@example.com', $this->findUser($id)['email']);
        }

        public function testUpdateUserRejectsAPhoneThatBelongsToSomeoneElse(): void
        {
            $this->insertUser(['phone_number' => '+27821112222']);
            $id = $this->insertUser();

            $response = $this->post($this->updatePayload($id, ['phone_number' => '082 111 2222']));

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('Mobile Number Exists', $response['json']['heading']);
        }

        public function testUpdateUserRejectsAnIdThatBelongsToSomeoneElse(): void
        {
            $this->insertUser(['id_number' => '8001015009087']);
            $id = $this->insertUser();

            $response = $this->post($this->updatePayload($id, ['id_number_hash' => '8001015009087']));

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('ID Number Exists', $response['json']['heading']);
        }

        public function testUpdateUserValidatesTheEmail(): void
        {
            $id = $this->insertUser();

            $response = $this->post($this->updatePayload($id, ['email' => 'not-an-email']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('Email is Not Valid', $response['json']['heading']);
        }

        public function testUpdateUserValidatesTheIdNumber(): void
        {
            $id = $this->insertUser();

            $response = $this->post($this->updatePayload($id, ['id_number_hash' => '1234567890123']));

            $this->assertSame(400, $response['json']['code']);
            $this->assertSame('ID Number is Not Valid', $response['json']['heading']);
        }

        public function testUpdateUserRejectsAnUnknownUser(): void
        {
            $response = $this->post($this->updatePayload(999999));

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('User Not Found', $response['json']['heading']);
        }

        public static function requiredUpdateParameters(): array
        {
            return [['user_id'], ['first_name'], ['last_name'], ['name'], ['phone_number'], ['message'], ['email']];
        }

        /** @dataProvider requiredUpdateParameters */
        #[DataProvider('requiredUpdateParameters')]
        public function testUpdateUserNeedsEveryParameter(string $parameter): void
        {
            $id = $this->insertUser();
            $payload = $this->updatePayload($id);
            unset($payload[$parameter]);

            $response = $this->post($payload);

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame("Parameter ($parameter) is REQUIRED.", $response['json']['message']);
        }

        // ---------- updateUserStatus ----------

        public function testDeactivateAndActivate(): void
        {
            $id = $this->insertUser(['is_active' => 1]);

            $off = $this->post(['function' => 'updateUserStatus', 'user_id' => $id, 'is_active' => 0]);
            $this->assertSame(['code' => 200, 'heading' => 'User Status Updated', 'message' => 'Successfully Deactivated user.'], $off['json']);
            $this->assertSame(0, (int)$this->findUser($id)['is_active']);

            $on = $this->post(['function' => 'updateUserStatus', 'user_id' => $id, 'is_active' => 1]);
            $this->assertSame('Successfully Activated user.', $on['json']['message']);
            $this->assertSame(1, (int)$this->findUser($id)['is_active']);
        }

        public function testUpdateUserStatusRejectsAnUnknownUser(): void
        {
            $response = $this->post(['function' => 'updateUserStatus', 'user_id' => 999999, 'is_active' => 0]);

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('User Not Found', $response['json']['heading']);
        }

        public function testUpdateUserStatusNeedsIsActive(): void
        {
            $id = $this->insertUser();

            $response = $this->post(['function' => 'updateUserStatus', 'user_id' => $id]);

            $this->assertSame('Parameter (is_active) is REQUIRED.', $response['json']['message']);
            $this->assertSame(1, (int)$this->findUser($id)['is_active']);
        }

        // ---------- updateUserArchived / updateUserUnArchived ----------

        public function testArchiveAndRestore(): void
        {
            $id = $this->insertUser();

            $archived = $this->post(['function' => 'updateUserArchived', 'user_id' => $id]);
            $this->assertSame(['code' => 200, 'heading' => 'User Archived', 'message' => 'Successfully archived user.'], $archived['json']);
            $this->assertNotNull($this->findUser($id)['deleted_at']);
            $this->assertSame([$id], array_column($this->getUsers('getAllUsersDeleted'), 'id'));

            $restored = $this->post(['function' => 'updateUserUnArchived', 'user_id' => $id]);
            $this->assertSame(['code' => 200, 'heading' => 'User Un-Archived', 'message' => 'Successfully un-archived user.'], $restored['json']);
            $this->assertNull($this->findUser($id)['deleted_at']);
            $this->assertSame([$id], array_column($this->getUsers('getAllUsersNonDeleted'), 'id'));
        }

        public function testArchiveIsASoftDelete(): void
        {
            $id = $this->insertUser();

            $this->post(['function' => 'updateUserArchived', 'user_id' => $id]);

            $this->assertSame(1, $this->userCount());
            $this->assertNotNull($this->findUser($id));
        }

        public static function archiveFunctions(): array
        {
            return [['updateUserArchived'], ['updateUserUnArchived']];
        }

        /** @dataProvider archiveFunctions */
        #[DataProvider('archiveFunctions')]
        public function testArchiveFunctionsRejectAnUnknownUser(string $function): void
        {
            $response = $this->post(['function' => $function, 'user_id' => 999999]);

            $this->assertSame(304, $response['json']['code']);
            $this->assertSame('User Not Found', $response['json']['heading']);
        }

        /** @dataProvider archiveFunctions */
        #[DataProvider('archiveFunctions')]
        public function testArchiveFunctionsNeedAUserId(string $function): void
        {
            $response = $this->post(['function' => $function]);

            $this->assertSame('Parameter (user_id) is REQUIRED.', $response['json']['message']);
        }
    }
