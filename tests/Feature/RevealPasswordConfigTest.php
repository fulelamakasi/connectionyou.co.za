<?php
    namespace Tests\Feature;

    use Tests\Support\ApiTestCase;

    /**
     * REVEAL_PASSWORD variants. Each test talks to its own server started with a
     * different environment, because the endpoint reads the value from the env.
     */
    final class RevealPasswordConfigTest extends ApiTestCase
    {
        public function testRevealIsDisabledWhenThePasswordIsEmpty(): void
        {
            $this->serverEnv = ['REVEAL_PASSWORD' => ''];
            $id = $this->insertUser();

            foreach (['', 'anything', self::REVEAL_PASSWORD] as $password) {
                $response = $this->reveal($id, 'phone_number', $password);

                $this->assertSame('503', $response['json']['code']);
                $this->assertStringContainsString('REVEAL_PASSWORD', $response['json']['message']);
            }

            $this->assertSame([], $this->logs(), 'A disabled reveal never counts towards the lockout.');
        }

        public function testPasswordHashValueIsAccepted(): void
        {
            $this->serverEnv = ['REVEAL_PASSWORD' => password_hash('hashed-in-env', PASSWORD_DEFAULT)];
            $id = $this->insertUser(['phone_number' => '+27781234509']);

            $this->assertSame('401', $this->reveal($id, 'phone_number', 'wrong')['json']['code']);

            $response = $this->reveal($id, 'phone_number', 'hashed-in-env');

            $this->assertSame('200', $response['json']['code']);
            $this->assertSame('+27781234509', $response['plain']['value']);
        }

        public function testTheHashStringItselfIsNotAValidPassword(): void
        {
            $hash = password_hash('hashed-in-env', PASSWORD_DEFAULT);
            $this->serverEnv = ['REVEAL_PASSWORD' => $hash];
            $id = $this->insertUser();

            $this->assertSame('401', $this->reveal($id, 'phone_number', $hash)['json']['code']);
        }
    }
