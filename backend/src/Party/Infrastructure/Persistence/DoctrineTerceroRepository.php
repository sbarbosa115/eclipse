<?php

namespace App\Party\Infrastructure\Persistence;

use App\Party\Domain\Error\TerceroNotFound;
use App\Party\Domain\Model\Tercero;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Domain\Fiscal\IdentificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineTerceroRepository implements TerceroRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Tercero
    {
        return $this->em->getRepository(Tercero::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new TerceroNotFound();
    }

    public function findByIdentification(Uuid $companyId, string $identificationType, string $identificationNumber, string $branchCode): ?Tercero
    {
        return $this->em->getRepository(Tercero::class)->findOneBy([
            'companyId' => $companyId,
            'identificationType' => IdentificationType::from($identificationType),
            'identificationNumber' => $identificationNumber,
            'branchCode' => $branchCode,
        ]);
    }

    public function add(Tercero $tercero): void
    {
        $this->em->persist($tercero);
    }

    public function remove(Tercero $tercero): void
    {
        $this->em->remove($tercero);
    }

    public function usingAccount(Uuid $companyId, Uuid $accountId): array
    {
        /** @var list<Tercero> $found */
        $found = $this->em->createQueryBuilder()
            ->select('t')->from(Tercero::class, 't')
            ->where('t.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->andWhere('t.receivableAccountId = :account OR t.payableAccountId = :account')->setParameter('account', $accountId, 'uuid')
            ->orderBy('t.id', 'ASC')
            ->getQuery()->getResult();

        return $found;
    }
}
