<?php

namespace App\Access\Infrastructure;

use App\Access\Application\Port\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * The hasher security.yaml configures for every user, so a hash made here is one the sign-in checks.
 */
final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(string $plainPassword): string
    {
        return $this->factory->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->hash($plainPassword);
    }
}
