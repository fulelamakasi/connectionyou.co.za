<?php
    namespace Tests\Unit;

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    /**
     * The helpers in config/bootstrap.php that both endpoints depend on.
     * No database or server needed.
     */
    final class BootstrapTest extends TestCase
    {
        // ---------- validateSAMobile ----------

        public static function validMobileNumbers(): array
        {
            return [
                'local'              => ['0781234509', '+27781234509'],
                'local with spaces'  => ['078 123 4509', '+27781234509'],
                'local with dashes'  => ['078-123-4509', '+27781234509'],
                'international'      => ['+27781234509', '+27781234509'],
                'international no +' => ['27781234509', '+27781234509'],
                'brackets'           => ['(078) 123 4509', '+27781234509'],
                'landline'           => ['0114567890', '+27114567890'],
            ];
        }

        /** @dataProvider validMobileNumbers */
        #[DataProvider('validMobileNumbers')]
        public function testValidateSAMobileNormalisesToInternationalWithoutSpaces(string $input, string $expected): void
        {
            $this->assertSame($expected, validateSAMobile($input));
        }

        public static function invalidMobileNumbers(): array
        {
            return [
                'empty'          => [''],
                'too short'      => ['078123450'],
                'too long'       => ['07812345091'],
                'starts with 09' => ['0981234509'],
                'starts with 00' => ['0081234509'],
                'letters'        => ['07812345ab'],
                'other country'  => ['+44781234509'],
            ];
        }

        /** @dataProvider invalidMobileNumbers */
        #[DataProvider('invalidMobileNumbers')]
        public function testValidateSAMobileRejectsInvalidNumbers(string $input): void
        {
            $this->assertFalse(validateSAMobile($input));
        }

        // ---------- validateEmail ----------

        public function testValidateEmail(): void
        {
            $this->assertSame('name@example.com', validateEmail('  name@example.com '));
            $this->assertFalse(validateEmail('name@'));
            $this->assertFalse(validateEmail('not an email'));
            $this->assertFalse(validateEmail(''));
        }

        // ---------- validateSAIDNumber ----------

        public function testValidateSAIDNumberAcceptsAValidNumber(): void
        {
            $this->assertTrue(validateSAIDNumber('8001015009087'));
            $this->assertTrue(validateSAIDNumber(' 8001015009087 '));
        }

        public static function invalidIdNumbers(): array
        {
            return [
                'empty'               => [''],
                '12 digits'           => ['800101500908'],
                '14 digits'           => ['80010150090870'],
                'letters'             => ['80010150090AB'],
                'bad check digit'     => ['8001015009088'],
                'impossible date'     => ['8013325009087'],
                'citizenship digit 3' => ['8001015009387'],
            ];
        }

        /** @dataProvider invalidIdNumbers */
        #[DataProvider('invalidIdNumbers')]
        public function testValidateSAIDNumberRejectsInvalidNumbers(string $idNumber): void
        {
            $this->assertFalse(validateSAIDNumber($idNumber));
        }

        public function testValidateSAIDNumberRejectsFutureBirthDates(): void
        {
            $tomorrow = new \DateTimeImmutable('tomorrow');

            if ($tomorrow->format('y') !== date('y')) {
                $this->markTestSkipped('Tomorrow is in a new year, so the YY century rule makes it a 1900s date.');
            }

            $base = $tomorrow->format('ymd') . '500908';
            $found = false;

            for ($check = 0; $check <= 9; $check++) {
                $found = $found || validateSAIDNumber($base . $check);
            }

            $this->assertFalse($found, 'An ID number with a birth date in the future must never validate.');
        }

        // ---------- maskPhoneNumber ----------

        public static function phoneMasks(): array
        {
            return [
                'stored format'       => ['+27781234509', '078*****09'],
                'old spaced format'   => ['+27 781234509', '078*****09'],
                'local format'        => ['0781234509', '078*****09'],
                'very short'          => ['1234', '****'],
            ];
        }

        /** @dataProvider phoneMasks */
        #[DataProvider('phoneMasks')]
        public function testMaskPhoneNumber(string $input, string $expected): void
        {
            $this->assertSame($expected, maskPhoneNumber($input));
        }

        // ---------- ecryptData / decryptData ----------

        public function testEncryptDecryptRoundTrip(): void
        {
            $data = ['function' => 'createUser', 'name' => 'Zanele Khumalo', 'nested' => [1, 2, 3]];
            $envelope = ecryptData($data);

            $this->assertSame(16, strlen(base64_decode($envelope['iv'])));
            $this->assertStringNotContainsString('Zanele', base64_decode($envelope['ciphertext']));
            $this->assertSame($data, decryptData($envelope['ciphertext'], $envelope['iv']));
        }

        public function testEncryptUsesAFreshIvEveryTime(): void
        {
            $first = ecryptData('same');
            $second = ecryptData('same');

            $this->assertNotSame($first['iv'], $second['iv']);
            $this->assertNotSame($first['ciphertext'], $second['ciphertext']);
        }

        public function testDecryptWithTheWrongKeyFails(): void
        {
            $envelope = ecryptData(['secret' => 'value']);
            $original = getenv('ENCRYPTION_KEY');

            putenv('ENCRYPTION_KEY=a-completely-different-32-char-k');

            try {
                $this->assertNull(decryptData($envelope['ciphertext'], $envelope['iv']));
            } finally {
                putenv('ENCRYPTION_KEY=' . $original);
            }
        }

        // ---------- encryptIdNumber / decryptIdNumber ----------

        public function testIdNumberEncryptionRoundTrip(): void
        {
            $encrypted = encryptIdNumber('8001015009087');

            $this->assertIsString($encrypted);
            $this->assertStringNotContainsString('8001015009087', base64_decode($encrypted));
            $this->assertSame('8001015009087', decryptIdNumber($encrypted));
            $this->assertNotSame($encrypted, encryptIdNumber('8001015009087'), 'Each encryption should use a new IV.');
        }

        public function testEncryptIdNumberReturnsNullForABlankId(): void
        {
            $this->assertNull(encryptIdNumber(''));
            $this->assertNull(encryptIdNumber('   '));
        }

        public function testDecryptIdNumberReturnsFalseForGarbage(): void
        {
            $this->assertFalse(decryptIdNumber(base64_encode(random_bytes(48))));
        }

        // ---------- hashIdNumber ----------

        public function testHashIdNumberIsDeterministicAndKeyed(): void
        {
            $hash = hashIdNumber('8001015009087');

            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
            $this->assertSame($hash, hashIdNumber(' 8001015009087 '));
            $this->assertNotSame(hash('sha256', '8001015009087'), $hash, 'Must be an HMAC, not a plain hash.');

            $original = getenv('APP_KEY');
            putenv('APP_KEY=base64:' . base64_encode('another-key-another-key-another!'));

            try {
                $this->assertNotSame($hash, hashIdNumber('8001015009087'));
            } finally {
                putenv('APP_KEY=' . $original);
            }
        }

        // ---------- verifyRevealPassword ----------

        public function testVerifyRevealPasswordWithPlaintextValue(): void
        {
            $this->withRevealPassword('plain-secret', function () {
                $this->assertTrue(isRevealPasswordConfigured());
                $this->assertTrue(verifyRevealPassword('plain-secret'));
                $this->assertFalse(verifyRevealPassword('Plain-secret'));
                $this->assertFalse(verifyRevealPassword(''));
            });
        }

        public function testVerifyRevealPasswordWithHashedValue(): void
        {
            $this->withRevealPassword(password_hash('hashed-secret', PASSWORD_DEFAULT), function () {
                $this->assertTrue(verifyRevealPassword('hashed-secret'));
                $this->assertFalse(verifyRevealPassword('wrong'));
            });
        }

        public function testVerifyRevealPasswordWhenNotConfigured(): void
        {
            $this->withRevealPassword('', function () {
                $this->assertFalse(isRevealPasswordConfigured());
                $this->assertFalse(verifyRevealPassword(''));
                $this->assertFalse(verifyRevealPassword('anything'));
            });
        }

        private function withRevealPassword(string $value, callable $test): void
        {
            $original = getenv('REVEAL_PASSWORD');
            putenv('REVEAL_PASSWORD=' . $value);

            try {
                $test();
            } finally {
                putenv('REVEAL_PASSWORD=' . $original);
            }
        }
    }
