<?php
    namespace Tests\Feature;

    use PHPUnit\Framework\Attributes\DataProvider;
    use Tests\Support\ApiTestCase;

    /**
     * GET getRestFulResponse.php?type=revealUserField – the password-protected
     * "Show number" / "Show ID number" endpoint.
     */
    final class RevealUserFieldTest extends ApiTestCase
    {
        public function testRevealsThePhoneNumberWithTheRightPassword(): void
        {
            $id = $this->insertUser(['phone_number' => '+27781234509']);

            $response = $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD);

            $this->assertSame('200', $response['json']['code']);
            $this->assertSame(['user_id' => $id, 'field' => 'phone_number', 'value' => '+27781234509'], $response['plain']);
        }

        public function testRevealsTheIdNumberWithTheRightPassword(): void
        {
            $id = $this->insertUser(['id_number' => '8001015009087']);

            $response = $this->reveal($id, 'id_number', self::REVEAL_PASSWORD);

            $this->assertSame('200', $response['json']['code']);
            $this->assertSame('8001015009087', $response['plain']['value']);
        }

        public function testRevealedValueIsEncryptedOnTheWire(): void
        {
            $id = $this->insertUser(['phone_number' => '+27781234509', 'id_number' => '8001015009087']);

            $phone = $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD);
            $idNumber = $this->reveal($id, 'id_number', self::REVEAL_PASSWORD);

            $this->assertStringNotContainsString('781234509', $phone['raw']);
            $this->assertStringNotContainsString('8001015009087', $idNumber['raw']);
            $this->assertSame(['ciphertext', 'iv'], array_keys($phone['json']['data']));
        }

        public function testWrongPasswordIsRejected(): void
        {
            $id = $this->insertUser();

            $response = $this->reveal($id, 'phone_number', 'not-the-password');

            $this->assertSame(['data' => null, 'message' => 'Incorrect password.', 'code' => '401'], $response['json']);
        }

        public function testEmptyPasswordIsRejected(): void
        {
            $id = $this->insertUser();

            $this->assertSame('401', $this->reveal($id, 'phone_number', '')['json']['code']);
        }

        public function testFiveWrongPasswordsLockTheIpOut(): void
        {
            $id = $this->insertUser();

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->assertSame('401', $this->reveal($id, 'phone_number', 'wrong')['json']['code'], "Attempt $attempt");
            }

            $locked = $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD);

            $this->assertSame('429', $locked['json']['code'], 'Even the right password is refused during the lockout.');
            $this->assertSame('Too many incorrect passwords. Try again in 15 minutes.', $locked['json']['message']);
        }

        public function testFailuresOlderThanTheLockoutWindowDoNotCount(): void
        {
            $id = $this->insertUser();
            $insert = $this->pdo()->prepare(
                "INSERT INTO `user_logs` (`request_data`, `response_data`, `request_method`, `response_code`, `endpoint`, `request_ip`, `created_at`)
                 VALUES ('{}', '{}', 'GET', 401, 'revealUserField', '127.0.0.1', NOW() - INTERVAL 16 MINUTE)"
            );

            for ($i = 0; $i < 5; $i++) {
                $insert->execute();
            }

            $this->assertSame('200', $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD)['json']['code']);
        }

        public function testEveryAttemptIsLoggedWithoutThePasswordOrTheValue(): void
        {
            $id = $this->insertUser(['phone_number' => '+27781234509']);

            $this->reveal($id, 'phone_number', 'wrong-guess');
            $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD);

            $logs = $this->logs();

            $this->assertSame([401, 200], array_map('intval', array_column($logs, 'response_code')));

            foreach ($logs as $log) {
                $this->assertSame('revealUserField', $log['endpoint']);
                $this->assertSame('GET', $log['request_method']);
                $this->assertSame('127.0.0.1', $log['request_ip']);
                $this->assertSame(['user_id' => $id, 'field' => 'phone_number'], json_decode($log['request_data'], true));

                $stored = json_encode($log);
                $this->assertStringNotContainsString('wrong-guess', $stored);
                $this->assertStringNotContainsString(self::REVEAL_PASSWORD, $stored);
                $this->assertStringNotContainsString('781234509', $stored);
            }
        }

        public static function staleTimestamps(): array
        {
            return [
                'ten minutes old'      => [-600],
                'ten minutes in future' => [600],
                'missing (zero)'       => [null],
            ];
        }

        /** @dataProvider staleTimestamps */
        #[DataProvider('staleTimestamps')]
        public function testStaleRequestsAreRejectedSoLoggedUrlsCanNotBeReplayed(?int $offset): void
        {
            $id = $this->insertUser();
            $timestamp = $offset === null ? 0 : time() + $offset;

            $response = $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD, $timestamp);

            $this->assertSame('400', $response['json']['code']);
            $this->assertStringContainsString('expired', $response['json']['message']);
            $this->assertSame([], $this->logs(), 'Expired requests are rejected before the password is checked.');
        }

        public function testRequestsWithinFiveMinutesAreAccepted(): void
        {
            $id = $this->insertUser();

            $this->assertSame('200', $this->reveal($id, 'phone_number', self::REVEAL_PASSWORD, time() - 240)['json']['code']);
        }

        public static function fieldsThatCanNotBeRevealed(): array
        {
            return [['email'], ['id_number_hash'], ['id_number_encrypted'], ['message'], ['']];
        }

        /** @dataProvider fieldsThatCanNotBeRevealed */
        #[DataProvider('fieldsThatCanNotBeRevealed')]
        public function testOnlyPhoneAndIdNumberCanBeRevealed(string $field): void
        {
            $id = $this->insertUser();

            $response = $this->reveal($id, $field, self::REVEAL_PASSWORD);

            $this->assertSame(['data' => null, 'message' => 'That field can not be shown.', 'code' => '400'], $response['json']);
        }

        public function testUnknownUserReturns404(): void
        {
            $response = $this->reveal(999999, 'phone_number', self::REVEAL_PASSWORD);

            $this->assertSame(['data' => null, 'message' => 'Invalid User Id.', 'code' => '404'], $response['json']);
        }

        public function testIdNumberThatWasNeverEncryptedReturns404(): void
        {
            // e.g. the seeded rows and entries saved before 2.1.0: hash only
            $id = $this->insertUser(['id_number_hash' => hash('sha256', 'legacy'), 'id_number_encrypted' => null]);

            $response = $this->reveal($id, 'id_number', self::REVEAL_PASSWORD);

            $this->assertSame('404', $response['json']['code']);
            $this->assertSame('No ID number on file that can be shown for this entry.', $response['json']['message']);
        }

        public static function malformedRequests(): array
        {
            $valid = ecryptData(['user_id' => 1, 'field' => 'phone_number', 'password' => 'x', 'timestamp' => time()]);

            return [
                'missing iv'           => [['query' => $valid['ciphertext']]],
                'short iv'             => [['query' => $valid['ciphertext'], 'iv' => base64_encode('short')]],
                'iv not base64'        => [['query' => $valid['ciphertext'], 'iv' => '***not base64***']],
                'garbage ciphertext'   => [['query' => base64_encode(random_bytes(32)), 'iv' => $valid['iv']]],
                'plaintext JSON query' => [['query' => '{"user_id":1,"field":"phone_number"}', 'iv' => $valid['iv']]],
            ];
        }

        /** @dataProvider malformedRequests */
        #[DataProvider('malformedRequests')]
        public function testMalformedRequestsAreRejected(array $params): void
        {
            $response = $this->get(['type' => 'revealUserField'] + $params);

            $this->assertSame(['data' => null, 'message' => 'Request data could not be read.', 'code' => '400'], $response['json']);
        }

        public function testRequestEncryptedWithADifferentKeyIsRejected(): void
        {
            $id = $this->insertUser();
            $original = getenv('ENCRYPTION_KEY');
            putenv('ENCRYPTION_KEY=an-attacker-guessed-32-char-key!');

            try {
                $forged = ecryptData(['user_id' => $id, 'field' => 'phone_number', 'password' => self::REVEAL_PASSWORD, 'timestamp' => time()]);
            } finally {
                putenv('ENCRYPTION_KEY=' . $original);
            }

            $response = $this->get(['type' => 'revealUserField', 'query' => $forged['ciphertext'], 'iv' => $forged['iv']]);

            $this->assertSame('400', $response['json']['code']);
        }
    }
