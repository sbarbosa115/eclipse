<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\Access\InvitesUsers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Monolog\Handler\TestHandler;

/**
 * Security audit 2026-10-04, finding 7: in production nothing recorded a refused sign-in or a refused action (the
 * log keeps only errors). Both are now warnings on the `security` channel, with who and what, never a password.
 */
final class SecurityLogTest extends ApiTestCase
{
    use InvitesUsers;
    use SignsUp;

    private function log(): TestHandler
    {
        return static::getContainer()->get('monolog.handler.security_test');
    }

    public function testARefusedSignInIsLoggedWithTheEmailButNotThePassword(): void
    {
        $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => 'nadie@acme.co', 'password' => 'secreto-que-no-va']);

        self::assertResponseStatusCodeSame(401);
        self::assertTrue($this->log()->hasWarningThatContains('Sign-in refused'));
        $record = $this->log()->getRecords()[0];
        self::assertSame('nadie@acme.co', $record->context['email']);
        self::assertStringNotContainsString('secreto-que-no-va', json_encode($record->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testARefusedActionIsLoggedWithWhoAndWhat(): void
    {
        $this->signUp();
        $this->signInInvitee('luis@acme.co', 'billing');

        $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'X', 'next_number' => 5]);
        self::assertResponseStatusCodeSame(403);
        $refused = $this->refusal();
        self::assertSame('PUT /api/v1/company/numbering/quotation', $refused['request']);
        self::assertSame('billing', $refused['role']);
        self::assertNotNull($refused['user_id']);

        $this->getJson('/api/v1/users');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('GET /api/v1/users', $this->refusal()['request'], 'A refusal by the voter (#[IsGranted]) too.');
    }

    /**
     * The one refusal the last request logged (the log is emptied at each request).
     *
     * @return array<string, mixed>
     */
    private function refusal(): array
    {
        $refusals = array_values(array_filter($this->log()->getRecords(), static fn ($r) => 'Access refused' === $r->message));
        self::assertCount(1, $refusals);

        return $refusals[0]->context;
    }
}
