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

    public function add(Account $account): void
    {
        $this->em->persist($account);
    }
}
