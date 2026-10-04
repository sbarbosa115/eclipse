<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Repository\AccessTokenRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineAccessTokenRepository implements AccessTokenRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByHash(string $tokenHash): ?AccessToken
    {
        return $this->em->getRepository(AccessToken::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function unusedOf(Uuid $userId, TokenPurpose $purpose): array
    {
        /* @var list<AccessToken> */
        return $this->em->getRepository(AccessToken::class)->findBy(['userId' => $userId, 'purpose' => $purpose, 'usedAt' => null]);
    }

    public function unusedOfUsers(array $userIds, TokenPurpose $purpose): array
    {
        if ([] === $userIds) {
            return [];
        }

        /* @var list<AccessToken> */
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(AccessToken::class, 't')
            ->where('t.userId IN (:users) AND t.purpose = :purpose AND t.usedAt IS NULL')
            ->setParameter('users', array_map(static fn (Uuid $id) => $id->toBinary(), $userIds), ArrayParameterType::BINARY)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->getResult();
    }

    public function add(AccessToken $token): void
    {
        $this->em->persist($token);
    }
}
