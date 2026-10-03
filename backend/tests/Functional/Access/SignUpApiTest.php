<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;

final class SignUpApiTest extends ApiTestCase
{
    use SignsUp;

    public function testSigningUpCreatesTheCompanyAndSignsTheOwnerIn(): void
    {
        $session = $this->signUp();

        self::assertSame('owner', $session['role'], 'Whoever signs a company up is its owner.');
        self::assertSame('Acme S.A.S.', $session['company_name']);
        self::assertSame('900123456', $session['company_nit']);
        self::assertSame('8', $session['company_check_digit'], 'The DV is computed from the NIT.');

        $me = $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful('The owner is signed in right after signing up.');
        self::assertSame('ana@acme.co', $me['email']);
    }

    public function testTheOwnerSignsInAgainWithTheirPassword(): void
    {
        $this->signUp();
        $this->signOut();
        self::assertResponseStatusCodeSame(204, 'Signing out answers the UI with no content, not a redirect.');

        $this->getJson('/api/v1/me');
        self::assertResponseStatusCodeSame(401, 'Signed out, /me asks to sign in.');

        $this->signIn('ANA@acme.co');
        $me = $this->getJson('/api/v1/me');
        self::assertSame('Acme S.A.S.', $me['company_name'] ?? null, 'E-mails are case-insensitive.');
    }

    public function testAWrongPasswordIsRefusedWithoutSayingWhichPartIsWrong(): void
    {
        $this->signUp();
        $this->signOut();

        $wrongPassword = $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => 'ana@acme.co', 'password' => 'nope nope nope']);
        self::assertResponseStatusCodeSame(401);
        $unknownEmail = $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => 'nobody@acme.co', 'password' => 'nope nope nope']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame($wrongPassword, $unknownEmail, 'An unknown e-mail and a wrong password answer alike.');
        self::assertSame('invalid_credentials', $wrongPassword['error']);
    }

    public function testAnEMailSignsUpOnlyOnce(): void
    {
        $this->signUp();
        $this->signOut();

        $body = $this->sendJson('POST', '/api/v1/auth/sign-up', ['company_name' => 'Otra', 'nit' => '800197268', 'owner_name' => 'Ana', 'email' => 'ana@acme.co', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('email', $body['violations'][0]['field'] ?? null, 'The e-mail field says it is taken.');
    }

    public function testACompanyNitSignsUpOnlyOnce(): void
    {
        $this->signUp();
        $this->signOut();

        $body = $this->sendJson('POST', '/api/v1/auth/sign-up', ['company_name' => 'Acme copia', 'nit' => '900.123.456', 'owner_name' => 'Luis', 'email' => 'luis@acme.co', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(422, 'The NIT is compared by its digits.');
        self::assertSame('identification_number', $body['violations'][0]['field'] ?? null);
    }

    public function testTheFormIsValidated(): void
    {
        $body = $this->sendJson('POST', '/api/v1/auth/sign-up', ['company_name' => '', 'nit' => 'abc', 'owner_name' => 'Ana', 'email' => 'not-an-email', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($body['violations'], 'field');
        foreach (['company_name', 'nit', 'email', 'password'] as $field) {
            self::assertContains($field, $fields, "$field is reported.");
        }
    }

    public function testAWriteFromAnotherSiteIsRefused(): void
    {
        $this->client->request('POST', '/api/v1/auth/sign-up', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://evil.example'], content: '{}');

        self::assertResponseStatusCodeSame(403, 'The session cookie must not be usable from another site.');
    }
}
