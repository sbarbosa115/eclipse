<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new UserNotFound();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => User::normalize($email)]);
    }

    public function add(User $user): void
    {
        $this->em->persist($user);
    }
}
