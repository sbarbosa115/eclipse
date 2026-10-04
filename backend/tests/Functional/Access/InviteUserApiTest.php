<?php

namespace App\Tests\Functional\Access;

use App\Shared\Domain\Model\AuditLog;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

final class InviteUserApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use InvitesUsers;
    use SignsUp;

    public function testTheOwnerInvitesTheAccountantWhoChoosesAPasswordAndLandsSignedIn(): void
    {
        $this->signUp();

        $user = $this->invite('Luis@Contadores.co', 'accountant');
        self::assertSame('luis@contadores.co', $user['email']);
        self::assertSame('accountant', $user['role']);
        self::assertSame('invited', $user['status'], 'Nobody signs in as an invitee before they accept.');

        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        \assert($mail instanceof Email);
        self::assertStringContainsString('Acme S.A.S.', (string) $mail->getSubject(), 'The subject names the company.');
        self::assertEmailTextBodyContains($mail, 'Ana Pérez', 'The e-mail says who invited them.');
        $token = $this->linkSentTo('luis@contadores.co', 'invitacion');

        $this->signOut();
        $lookup = $this->sendJson('POST', '/api/v1/auth/invitations/lookup', ['token' => $token]);
        self::assertResponseIsSuccessful('The public page reads the invitation by its token.');
        self::assertSame(['email' => 'luis@contadores.co', 'company_name' => 'Acme S.A.S.', 'role' => 'accountant'], $lookup);

        $session = $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => ' Luis Gómez ', 'password' => 'correct horse battery']);
        self::assertResponseIsSuccessful();
        self::assertSame('accountant', $session['role']);
        self::assertSame('Luis Gómez', $session['name']);
        self::assertSame('Acme S.A.S.', $session['company_name'], 'The invitee joins the company that invited them.');

        $me = $this->getJson('/api/v1/me');
        self::assertResponseIsSuccessful('Accepting signs the invitee in.');
        self::assertSame('luis@contadores.co', $me['email']);

        $this->signOut();
        $this->signIn('luis@contadores.co');
    }

    public function testAnInvitationLinkWorksOnce(): void
    {
        $this->signUp();
        $this->invite('luis@acme.co');
        $token = $this->linkSentTo('luis@acme.co', 'invitacion');
        $this->acceptAs($token);

        $this->signOut();
        $body = $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => 'Otro', 'password' => 'another long password']);
        self::assertResponseStatusCodeSame(404, 'A used link is spent.');
        self::assertSame('link_invalid', $body['error']);
        $this->sendJson('POST', '/api/v1/auth/invitations/lookup', ['token' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnInvitationLinkLastsSevenDays(): void
    {
        self::mockTime('2026-10-03 09:00:00');
        $this->signUp();
        $this->invite('luis@acme.co');
        $token = $this->linkSentTo('luis@acme.co', 'invitacion');
        $this->signOut();

        self::mockTime('2026-10-10 08:59:00');
        $this->sendJson('POST', '/api/v1/auth/invitations/lookup', ['token' => $token]);
        self::assertResponseIsSuccessful('A minute before seven days it still works.');

        self::mockTime('2026-10-10 09:00:00');
        $body = $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => 'Luis', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(404, 'Seven days later the link has expired.');
        self::assertSame('link_invalid', $body['error']);
    }

    public function testResendingAnInvitationReplacesTheEarlierLink(): void
    {
        $this->signUp();
        $user = $this->invite('luis@acme.co');
        $first = $this->linkSentTo('luis@acme.co', 'invitacion');

        $resent = $this->sendJson('POST', "/api/v1/users/{$user['id']}/invitation", []);
        self::assertResponseIsSuccessful();
        self::assertSame('invited', $resent['status']);
        $second = $this->linkSentTo('luis@acme.co', 'invitacion');
        self::assertNotSame($first, $second, 'A new link is sent.');

        $this->signOut();
        $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $first, 'name' => 'Luis', 'password' => 'correct horse battery']);
        self::assertResponseStatusCodeSame(404, 'The earlier link no longer works.');
        $this->acceptAs($second);
    }

    public function testOnlySomeoneStillInvitedGetsTheInvitationAgain(): void
    {
        $session = $this->signUp();

        $body = $this->sendJson('POST', "/api/v1/users/{$session['user_id']}/invitation", []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('not_an_invitation', $body['error']);
    }

    public function testAnEMailAlreadyRegisteredAnywhereIsRefused(): void
    {
        $this->signUp('otra@empresa.co', '800197268', 'Otra Empresa');
        $this->signOut();
        $this->signUp();
        $this->invite('luis@acme.co');

        foreach (['otra@empresa.co' => 'the owner of another company', 'LUIS@acme.co' => 'someone already invited here', 'ana@acme.co' => 'the owner herself'] as $email => $who) {
            $body = $this->sendJson('POST', '/api/v1/users/invitations', ['email' => $email, 'role' => 'billing']);
            self::assertResponseStatusCodeSame(422, "The e-mail of $who is refused.");
            self::assertSame('email', $body['violations'][0]['field'] ?? null);
            self::assertSame('Este correo ya está registrado.', $body['violations'][0]['message'] ?? null);
        }
    }

    public function testTheInvitationFormIsValidated(): void
    {
        $this->signUp();

        $body = $this->sendJson('POST', '/api/v1/users/invitations', ['email' => 'not-an-email', 'role' => 'owner']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('email', $fields);
        self::assertContains('role', $fields, 'Invitations are for billing users and accountants; the owner is who signed up.');
        self::assertEmailCount(0);
    }

    public function testOnlyTheOwnerInvites(): void
    {
        $this->signUp();
        $this->signInInvitee('luis@acme.co', 'billing');

        $this->sendJson('POST', '/api/v1/users/invitations', ['email' => 'eva@acme.co', 'role' => 'billing']);

        self::assertResponseStatusCodeSame(403, 'A billing user cannot invite.');
    }

    public function testTheAcceptFormIsValidated(): void
    {
        $this->signUp();
        $this->invite('luis@acme.co');
        $token = $this->linkSentTo('luis@acme.co', 'invitacion');
        $this->signOut();

        $body = $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => '', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('name', $fields);
        self::assertContains('password', $fields);
        $this->sendJson('POST', '/api/v1/auth/invitations/lookup', ['token' => $token]);
        self::assertResponseIsSuccessful('A refused form does not spend the link.');
    }

    public function testAnUnknownLinkIsRefusedLikeAnExpiredOne(): void
    {
        $body = $this->sendJson('POST', '/api/v1/auth/invitations/lookup', ['token' => str_repeat('x', 43)]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('link_invalid', $body['error']);
    }

    public function testAnotherCompanysInviteeIsNotFound(): void
    {
        $this->signUp('otra@empresa.co', '800197268', 'Otra Empresa');
        $theirs = $this->invite('luis@otra.co');
        $this->signOut();
        $this->signUp();

        $this->sendJson('POST', "/api/v1/users/{$theirs['id']}/invitation", []);

        self::assertResponseStatusCodeSame(404, "Another company's user is not found, not forbidden.");
    }

    public function testTheInvitationAndItsAcceptanceAreAudited(): void
    {
        $owner = $this->signUp();
        $user = $this->invite('luis@acme.co', 'accountant');
        $this->acceptAs($this->linkSentTo('luis@acme.co', 'invitacion'));

        $rows = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findBy(['subjectType' => 'user'], ['occurredAt' => 'ASC']);
        $actions = array_map(static fn (AuditLog $l) => $l->action(), $rows);
        self::assertSame(['user.invited', 'user.invitation_accepted'], $actions);
        self::assertSame($owner['user_id'], $rows[0]->userId()?->toRfc4122(), 'The owner invited.');
        self::assertSame(['email' => 'luis@acme.co', 'role' => 'accountant'], $rows[0]->data());
        self::assertSame($user['id'], $rows[1]->userId()?->toRfc4122(), 'The invitee accepted.');
    }
}
