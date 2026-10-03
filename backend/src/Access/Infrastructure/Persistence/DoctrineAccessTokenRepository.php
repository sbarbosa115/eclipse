<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Repository\AccessTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAccessTokenRepository implements AccessTokenRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByHash(string $tokenHash): ?AccessToken
    {
        return $this->em->getRepository(AccessToken::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function add(AccessToken $token): void
    {
        $this->em->persist($token);
    }
}
