<?php

namespace App\Access\Application\Query;

use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Users as the security layer and other contexts read them (who created a document, by name).
 */
final class Users
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function byEmail(string $email): ?UserView
    {
        $user = $this->users->findByEmail($email);

        return null === $user ? null : self::toView($user);
    }

    public function get(Uuid $companyId, Uuid $userId): UserView
    {
        return self::toView($this->users->get($companyId, $userId));
    }

    /** Any company's user, by id: the one an e-mailed link names. */
    public function byId(Uuid $userId): UserView
    {
        return self::toView($this->users->getById($userId));
    }

    private static function toView(User $u): UserView
    {
        return new UserView($u->id()->toRfc4122(), $u->companyId()->toRfc4122(), $u->email(), $u->name(), $u->role()->value, $u->status()->value, $u->canSignIn(), $u->passwordHash());
    }
}
