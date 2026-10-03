<?php

namespace App\Access\UI\Http\Security;

use App\Access\Application\Query\Users;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users by e-mail for json_login, and reloads the session's user on every request so a deactivated user, a
 * changed role or a changed password takes effect at once.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly Users $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->byEmail($identifier);
        if (null === $user || !$user->canSignIn) {
            $e = new UserNotFoundException();
            $e->setUserIdentifier($identifier);
            throw $e;
        }

        return SecurityUser::from($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(get_debug_type($user));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
