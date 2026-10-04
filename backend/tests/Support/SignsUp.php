<?php

namespace App\Tests\Support;

/**
 * Signs a new company up through the API (which signs its owner in), the way a person starts using the app.
 *
 * @mixin ApiTestCase
 */
trait SignsUp
{
    /**
     * @return array<mixed> the SessionOutput
     */
    protected function signUp(string $email = 'ana@acme.co', string $nit = '900123456', string $company = 'Acme S.A.S.'): array
    {
        $session = $this->sendJson('POST', '/api/v1/auth/sign-up', [
            'company_name' => $company,
            'nit' => $nit,
            'owner_name' => 'Ana Pérez',
            'email' => $email,
            'password' => 'correct horse battery',
        ]);
        self::assertResponseStatusCodeSame(201, "$email signs $company up.");

        return $session;
    }

    protected function signOut(): void
    {
        $this->client->request('POST', '/api/v1/auth/sign-out');
    }

    protected function signIn(string $email, string $password = 'correct horse battery'): void
    {
        $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful("$email signs in.");
    }
}
