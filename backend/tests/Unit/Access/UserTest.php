<?php

namespace App\Tests\Unit\Access;

use App\Access\Domain\Error\NotAnInvitation;
use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-03 10:00:00');
    }

    public function testAnInviteeCannotSignInUntilTheyAccept(): void
    {
        $user = User::invite(Uuid::v7(), ' Luis@Acme.co ', '', Role::Accountant, $this->now);

        self::assertSame('luis@acme.co', $user->email(), 'E-mails are kept trimmed and lower-case.');
        self::assertSame(UserStatus::Invited, $user->status());
        self::assertFalse($user->canSignIn(), 'An invitee has no password yet.');

        $user->acceptInvitation('Luis Gómez', 'hash');

        self::assertSame(UserStatus::Active, $user->status());
        self::assertSame('Luis Gómez', $user->name());
        self::assertTrue($user->canSignIn());
    }

    public function testOnlyAnInviteeAcceptsAnInvitation(): void
    {
        $owner = User::owner(Uuid::v7(), 'ana@acme.co', 'Ana', 'hash', $this->now);

        $this->expectException(NotAnInvitation::class);
        $owner->acceptInvitation('Ana', 'other');
    }

    public function testADeactivatedUserCannotSignInAndComesBackAsTheyWere(): void
    {
        $active = User::owner(Uuid::v7(), 'ana@acme.co', 'Ana', 'hash', $this->now);
        $invited = User::invite(Uuid::v7(), 'luis@acme.co', '', Role::Billing, $this->now);

        $active->deactivate();
        $invited->deactivate();
        self::assertFalse($active->canSignIn());
        self::assertSame(UserStatus::Deactivated, $invited->status());

        $active->reactivate();
        $invited->reactivate();
        self::assertTrue($active->canSignIn(), 'A user with a password is active again.');
        self::assertSame(UserStatus::Invited, $invited->status(), 'An invitee who never accepted is invited again, not active.');
    }

    public function testAnActiveOwnerIsTheOnlyKindThatCountsAsOne(): void
    {
        $owner = User::owner(Uuid::v7(), 'ana@acme.co', 'Ana', 'hash', $this->now);
        self::assertTrue($owner->isActiveOwner());

        $owner->changeRole(Role::Accountant);
        self::assertFalse($owner->isActiveOwner(), 'Demoted.');

        $owner->changeRole(Role::Owner);
        $owner->deactivate();
        self::assertFalse($owner->isActiveOwner(), 'Deactivated.');
    }

    public function testANewPasswordReplacesTheOldHash(): void
    {
        $user = User::owner(Uuid::v7(), 'ana@acme.co', 'Ana', 'old', $this->now);

        $user->changePassword('new');

        self::assertSame('new', $user->passwordHash());
    }

    public function testALinkIsUsableOnceAndUntilItExpires(): void
    {
        $token = AccessToken::issue(Uuid::v7(), TokenPurpose::Invitation, 'secret', $this->now, new \DateInterval('P7D'));

        self::assertSame(AccessToken::hash('secret'), $token->tokenHash(), 'Only the hash is stored.');
        self::assertTrue($token->isUsable($this->now->modify('+6 days 23 hours')));
        self::assertFalse($token->isUsable($this->now->modify('+7 days')), 'Seven days later it has expired.');

        $token->use($this->now);
        self::assertFalse($token->isUsable($this->now), 'A used link is spent.');
    }
}
