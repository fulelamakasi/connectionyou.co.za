<?php
    namespace Tests\Support;

    use PDO;
    use PHPUnit\Framework\TestCase;

    /**
     * Base class for endpoint tests: a clean database per test, an HTTP client for
     * getRestFulResponse.php / processRestFulResponse.php, and the same AES envelope
     * the browser uses (ecryptData / decryptData from config/bootstrap.php).
     */
    abstract class ApiTestCase extends TestCase
    {
        public const REVEAL_PASSWORD = 'test-reveal-password';

        /** Env overrides for the server this test class talks to. */
        protected $serverEnv = [];

        protected function setUp(): void
        {
            parent::setUp();
            TestDatabase::reset();
        }

        protected function pdo(): PDO
        {
            return TestDatabase::pdo();
        }

        // ---------- HTTP ----------

        /**
         * @return array{status: int, raw: string, json: mixed}
         */
        protected function get(array $params): array
        {
            return $this->request('GET', 'getRestFulResponse.php?' . http_build_query($params));
        }

        /** POST an encrypted payload, exactly like postRequests.js. */
        protected function post(array $payload): array
        {
            return $this->postForm(ecryptData($payload));
        }

        /** POST raw form fields (for testing malformed requests). */
        protected function postForm(array $form): array
        {
            return $this->request('POST', 'processRestFulResponse.php', http_build_query($form));
        }

        private function request(string $method, string $path, ?string $body = null): array
        {
            $options = [
                'method'        => $method,
                'ignore_errors' => true,
                'timeout'       => 10,
                'header'        => "Accept: application/json\r\n",
            ];

            if ($body !== null) {
                $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $options['content'] = $body;
            }

            $raw = file_get_contents(TestServer::url($this->serverEnv) . $path, false, stream_context_create(['http' => $options]));
            $status = 0;

            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
                $status = (int)$matches[1];
            }

            $this->assertNotFalse($raw, 'No response from ' . $path . "\n" . TestServer::logTail($this->serverEnv));

            return ['status' => $status, 'raw' => $raw, 'json' => json_decode($raw, true)];
        }

        /** Decrypts a {ciphertext, iv} envelope from a GET response. */
        protected function decrypt($envelope)
        {
            $this->assertIsArray($envelope, 'Expected an encrypted {ciphertext, iv} envelope.');
            $this->assertArrayHasKey('ciphertext', $envelope);
            $this->assertArrayHasKey('iv', $envelope);

            return decryptData($envelope['ciphertext'], $envelope['iv']);
        }

        /** GET a list type and return the decrypted rows ([] when the API says none found). */
        protected function getUsers(string $type, $query = null): array
        {
            $params = ['type' => $type];

            if ($query !== null) {
                $params['query'] = $query;
            }

            $response = $this->get($params);
            $this->assertSame(200, $response['status']);

            if (($response['json']['code'] ?? null) === '400') {
                $this->assertNull($response['json']['data']);
                return [];
            }

            $this->assertSame('200', $response['json']['code'] ?? null, $response['raw']);

            return $this->decrypt($response['json']['data']);
        }

        /** Calls the encrypted revealUserField GET type and decrypts the answer. */
        protected function reveal(int $userId, string $field, string $password, ?int $timestamp = null): array
        {
            $request = ecryptData([
                'user_id'   => $userId,
                'field'     => $field,
                'password'  => $password,
                'timestamp' => $timestamp ?? time(),
            ]);

            $response = $this->get(['type' => 'revealUserField', 'query' => $request['ciphertext'], 'iv' => $request['iv']]);
            $response['plain'] = null;

            if (($response['json']['code'] ?? null) === '200') {
                $response['plain'] = $this->decrypt($response['json']['data']);
            }

            return $response;
        }

        // ---------- Fixtures ----------

        /** Inserts a user straight into the table and returns its id. */
        protected function insertUser(array $overrides = []): int
        {
            static $sequence = 0;
            $sequence++;

            $idNumber = array_key_exists('id_number', $overrides) ? $overrides['id_number'] : null;
            unset($overrides['id_number']);

            $row = array_merge([
                'email'               => "user{$sequence}@example.com",
                'first_name'          => 'Test',
                'last_name'           => "User{$sequence}",
                'name'                => "Test User{$sequence}",
                'phone_number'        => sprintf('+278200000%02d', $sequence % 100),
                'id_number_hash'      => $idNumber ? hashIdNumber($idNumber) : hashIdNumber(''),
                'id_number_encrypted' => $idNumber ? encryptIdNumber($idNumber) : null,
                'message'             => 'A test enquiry.',
                'is_active'           => 1,
                'created_at'          => date('Y-m-d H:i:s'),
                'deleted_at'          => null,
                'editedby'            => '',
            ], $overrides);

            $columns = array_keys($row);
            $sql = 'INSERT INTO `users` (`' . implode('`, `', $columns) . '`) VALUES (:' . implode(', :', $columns) . ')';
            $this->pdo()->prepare($sql)->execute($row);

            return (int)$this->pdo()->lastInsertId();
        }

        protected function findUser(int $id): ?array
        {
            $stmt = $this->pdo()->prepare('SELECT * FROM `users` WHERE `id` = :id');
            $stmt->execute([':id' => $id]);

            return $stmt->fetch() ?: null;
        }

        protected function findUserByEmail(string $email): ?array
        {
            $stmt = $this->pdo()->prepare('SELECT * FROM `users` WHERE `email` = :email');
            $stmt->execute([':email' => $email]);

            return $stmt->fetch() ?: null;
        }

        protected function userCount(): int
        {
            return (int)$this->pdo()->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
        }

        protected function logs(): array
        {
            return $this->pdo()->query('SELECT * FROM `user_logs` ORDER BY `id`')->fetchAll();
        }

        /** Payload the enquiry form sends for createUser (see buildUserPayload in postRequests.js). */
        protected function userPayload(array $overrides = []): array
        {
            return array_merge([
                'function'       => 'createUser',
                'first_name'     => 'Thandiwe',
                'last_name'      => 'Nkosi',
                'name'           => 'Thandiwe Nkosi',
                'email'          => 'thandiwe@example.com',
                'phone_number'   => '+27781234509',
                'id_number_hash' => '8001015009087',
                'message'        => 'I would like a quote.',
            ], $overrides);
        }

        /**
         * A valid SA ID number for the given birth date (YYMMDD), with the Luhn check digit
         * worked out by the app's own validator.
         */
        protected static function makeIdNumber(string $birthDate = '900202', string $sequence = '5009'): string
        {
            $base = $birthDate . $sequence . '08';

            for ($check = 0; $check <= 9; $check++) {
                if (validateSAIDNumber($base . $check)) {
                    return $base . $check;
                }
            }

            throw new \RuntimeException("No valid check digit for $base");
        }
    }
