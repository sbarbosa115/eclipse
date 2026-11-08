<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineAccountRepository implements AccountRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $accountId): Account
    {
        return $this->em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'id' => $accountId]) ?? throw new AccountNotFound();
    }

    public function byCode(Uuid $companyId, string $code): ?Account
    {
        return $this->em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'code' => $code]);
    }

    public function hasActiveChildren(Uuid $companyId, string $code): bool
    {
        // ledger_account_parent (company_id, parent_code) answers it.
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')->from(Account::class, 'a')
            ->where('a.companyId = :company')->andWhere('a.parentCode = :code')->andWhere('a.active = true')
            ->setParameter('company', $companyId, 'uuid')->setParameter('code', $code)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    public function add(Account $account): void
    {
        $this->em->persist($account);
    }
}
