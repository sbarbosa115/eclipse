<?php

namespace App\Tests\Functional\Access;

use App\Shared\Domain\Model\AuditLog;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

final class PasswordResetApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use InvitesUsers;
    use SignsUp;

    /** @return array<mixed> */
    private function requestReset(string $email): array
    {
        $body = $this->sendJson('POST', '/api/v1/auth/password-reset', ['email' => $email]);
        self::assertResponseStatusCodeSame(202, "A reset for $email is accepted.");

        return $body;
    }

    public function testAResetLinkSetsANewPasswordAndSignsIn(): void
    {
        $this->signUp();
        $this->signOut();

        $this->requestReset('ANA@acme.co');
        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        \assert($mail instanceof Email);
        self::assertStringContainsString('contraseña', (string) $mail->getSubject());
        $token = $this->linkSentTo('ana@acme.co', 'restablecer-contrasena');

        $this->sendJson('POST', '/api/v1/auth/password-reset/check', ['token' => $token]);
        self::assertResponseStatusCodeSame(204, 'The page checks the link before asking for a password.');

        $session = $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $token, 'password' => 'a brand new password']);
        self::assertResponseIsSuccessful();
        self::assertSame('ana@acme.co', $session['email']);
        $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful('Setting the password signs the person in.');

        $this->signOut();
        $this->sendJson('POST', '/api/v1/auth/sign-in', ['email' => 'ana@acme.co', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(401, 'The old password no longer works.');
        $this->signIn('ana@acme.co', 'a brand new password');
    }

    public function testEveryoneGetsTheSameAnswerWhetherOrNotTheEMailHasAnAccount(): void
    {
        $this->signUp();
        $this->signOut();

        $known = $this->requestReset('ana@acme.co');
        $unknown = $this->requestReset('nadie@acme.co');

        self::assertSame($known, $unknown, 'The answer never tells which e-mails have an account.');
        self::assertEmailCount(0, null, 'Nobody is e-mailed for an unknown address.');
    }

    public function testTheFormNeedsAnEMail(): void
    {
        $body = $this->sendJson('POST', '/api/v1/auth/password-reset', ['email' => 'nope']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('email', $body['violations'][0]['field'] ?? null);
    }

    public function testAResetLinkLastsOneHour(): void
    {
        self::mockTime('2026-10-03 09:00:00');
        $this->signUp();
        $this->signOut();
        $this->requestReset('ana@acme.co');
        $token = $this->linkSentTo('ana@acme.co', 'restablecer-contrasena');

        self::mockTime('2026-10-03 10:00:00');
        $body = $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $token, 'password' => 'a brand new password']);

        self::assertResponseStatusCodeSame(404, 'An hour later the link has expired.');
        self::assertSame('link_invalid', $body['error']);
    }

    public function testAResetLinkWorksOnceAndANewerOneReplacesIt(): void
    {
        $this->signUp();
        $this->signOut();
        $this->requestReset('ana@acme.co');
        $first = $this->linkSentTo('ana@acme.co', 'restablecer-contrasena');
        $this->requestReset('ana@acme.co');
        $second = $this->linkSentTo('ana@acme.co', 'restablecer-contrasena');

        $this->sendJson('POST', '/api/v1/auth/password-reset/check', ['token' => $first]);
        self::assertResponseStatusCodeSame(404, 'Asking again replaces the earlier link.');

        $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $second, 'password' => 'a brand new password']);
        self::assertResponseIsSuccessful();
        $this->signOut();
        $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $second, 'password' => 'yet another password']);
        self::assertResponseStatusCodeSame(404, 'A used link is spent.');
    }

    public function testEveryOtherSessionOfThePersonEnds(): void
    {
        $this->signUp();
        $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful();
        $jar = $this->client->getCookieJar();
        $laptop = $jar->get('MOCKSESSID') ?? $jar->all()[0] ?? null;
        self::assertInstanceOf(Cookie::class, $laptop, 'The owner has a session cookie.');

        // On another device (no cookie), the owner resets the password.
        $jar->clear();
        $this->requestReset('ana@acme.co');
        $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $this->linkSentTo('ana@acme.co', 'restablecer-contrasena'), 'password' => 'a brand new password']);
        self::assertResponseIsSuccessful();

        $jar->clear();
        $jar->set($laptop);
        $body = $this->getJson('/api/v1/me');
        self::assertResponseStatusCodeSame(401, 'The session signed in with the old password has ended.');
        self::assertSame('unauthorized', $body['error']);
    }

    public function testADeactivatedUserIsNotSentALink(): void
    {
        $this->signUp();
        $user = $this->invite('luis@acme.co');
        $this->acceptAs($this->linkSentTo('luis@acme.co', 'invitacion'));
        $this->signOut();
        $this->signIn('ana@acme.co');
        $this->sendJson('POST', "/api/v1/users/{$user['id']}/deactivate", []);
        self::assertResponseIsSuccessful();
        $this->signOut();

        $this->requestReset('luis@acme.co');

        self::assertEmailCount(0, null, 'A deactivated user cannot get back in through a reset.');
    }

    public function testAnInvitationLinkIsNotAResetLink(): void
    {
        $this->signUp();
        $this->invite('luis@acme.co');
        $token = $this->linkSentTo('luis@acme.co', 'invitacion');
        $this->signOut();

        $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $token, 'password' => 'a brand new password']);

        self::assertResponseStatusCodeSame(404, 'Each link does only what it was sent for.');
    }

    public function testTooManyRequestsFromOneAddressAreRefused(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->requestReset("persona$i@acme.co");
        }

        $body = $this->sendJson('POST', '/api/v1/auth/password-reset', ['email' => 'persona6@acme.co']);

        self::assertResponseStatusCodeSame(429, 'A script mailing people is stopped.');
        self::assertSame('too_many_requests', $body['error']);
    }

    public function testOnePersonIsNotMailedOverAndOver(): void
    {
        $this->signUp();
        $this->signOut();
        $sent = 0;
        for ($i = 1; $i <= 6; ++$i) {
            $this->client->setServerParameter('REMOTE_ADDR', "10.0.0.$i");
            $this->requestReset('ana@acme.co');
            $sent += \count(self::getMailerMessages());
        }

        self::assertSame(5, $sent, 'From many addresses, one person still gets at most five e-mails in ten minutes, and the answer is the same.');
    }

    public function testTheNewPasswordIsValidatedAndARefusalKeepsTheLink(): void
    {
        $this->signUp();
        $this->signOut();
        $this->requestReset('ana@acme.co');
        $token = $this->linkSentTo('ana@acme.co', 'restablecer-contrasena');

        $body = $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $token, 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('password', $body['violations'][0]['field'] ?? null);
        $this->sendJson('POST', '/api/v1/auth/password-reset/check', ['token' => $token]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testTheResetIsAudited(): void
    {
        $owner = $this->signUp();
        $this->signOut();
        $this->requestReset('ana@acme.co');
        $this->sendJson('POST', '/api/v1/auth/password-reset/confirm', ['token' => $this->linkSentTo('ana@acme.co', 'restablecer-contrasena'), 'password' => 'a brand new password']);

        $rows = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findBy(['action' => 'user.password_reset']);
        self::assertCount(1, $rows);
        self::assertSame($owner['user_id'], $rows[0]->subjectId()?->toRfc4122());
        self::assertSame([], $rows[0]->data(), 'Nothing about the password is logged.');
    }
}
