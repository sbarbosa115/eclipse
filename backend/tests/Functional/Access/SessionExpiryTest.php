<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/** §4.14: a session that sees no request for two hours ends. */
final class SessionExpiryTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use SignsUp;

    public function testASessionUsedWithinTwoHoursStaysOpen(): void
    {
        self::mockTime('2026-10-03 08:00:00');
        $this->signUp();

        self::mockTime('2026-10-03 09:59:00');
        $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful('An hour and 59 minutes later it is still open.');

        self::mockTime('2026-10-03 11:58:00');
        $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful('Every request starts the two hours again.');
    }

    public function testASessionLeftAloneForTwoHoursEnds(): void
    {
        self::mockTime('2026-10-03 08:00:00');
        $this->signUp();

        self::mockTime('2026-10-03 10:00:00');
        $body = $this->getJson('/api/v1/me');

        self::assertResponseStatusCodeSame(401, 'After two hours without a request the session is over.');
        self::assertSame('session_expired', $body['error'], 'The UI can tell the person why they must sign in again.');

        self::mockTime('2026-10-03 10:01:00');
        $this->getJson('/api/v1/me');
        self::assertResponseStatusCodeSame(401, 'It stays over: the session was ended, not just refused once.');
    }

    public function testSigningInAgainAfterExpiryWorks(): void
    {
        self::mockTime('2026-10-03 08:00:00');
        $this->signUp();

        self::mockTime('2026-10-03 12:00:00');
        $this->signIn('ana@acme.co');

        $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful();
    }

    public function testAPublicRequestAfterExpiryIsNotRefused(): void
    {
        self::mockTime('2026-10-03 08:00:00');
        $this->signUp();

        self::mockTime('2026-10-03 12:00:00');
        $this->sendJson('POST', '/api/v1/auth/password-reset', ['email' => 'ana@acme.co']);

        self::assertResponseStatusCodeSame(202, 'An expired session does not block the public pages.');
    }
}
