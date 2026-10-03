<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Mime\Email;

/**
 * Invites people the way the owner does, and reads the link the e-mail carries.
 *
 * @mixin ApiTestCase
 */
trait InvitesUsers
{
    /**
     * @return array<mixed> the UserOutput
     */
    protected function invite(string $email, string $role = 'billing'): array
    {
        $user = $this->sendJson('POST', '/api/v1/users/invitations', ['email' => $email, 'role' => $role]);
        self::assertResponseStatusCodeSame(201, "The owner invites $email.");

        return $user;
    }

    /** The token in the last e-mail sent to this address (by the request just made). */
    protected function linkSentTo(string $email, string $page): string
    {
        $mails = array_values(array_filter(
            self::getMailerMessages(),
            static fn ($m) => $m instanceof Email && $m->getTo()[0]->getAddress() === $email,
        ));
        self::assertNotEmpty($mails, "An e-mail was sent to $email.");
        $mail = $mails[\count($mails) - 1];
        self::assertSame(1, preg_match('~/'.preg_quote($page, '~').'#([A-Za-z0-9_-]{40,})~', (string) $mail->getTextBody(), $m), "The e-mail links to /$page with the token after #.");
        self::assertStringContainsString($m[1], (string) $mail->getHtmlBody(), 'The HTML part carries the same link.');

        return $m[1];
    }

    /**
     * Signs the owner out and the invitee in through their invitation (the session is theirs afterwards).
     *
     * @return array<mixed> the SessionOutput
     */
    protected function acceptAs(string $token, string $name = 'Luis Gómez', string $password = 'correct horse battery'): array
    {
        $this->signOut();
        $session = $this->sendJson('POST', '/api/v1/auth/invitations/accept', ['token' => $token, 'name' => $name, 'password' => $password]);
        self::assertResponseIsSuccessful('The invitee accepts.');

        return $session;
    }

    /** A second person in the owner's company, with this role, signed in (the owner is signed out). */
    protected function signInInvitee(string $email, string $role): void
    {
        $this->invite($email, $role);
        $this->acceptAs($this->linkSentTo($email, 'invitacion'));
    }
}
