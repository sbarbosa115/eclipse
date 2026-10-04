<?php

namespace App\Tests\Support;

use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * An account of a company's chart for a test: the one the PUC seed already gave every company (Ledger
 * ProvisionLedger), or, for a code the seed has not, a new one. Creating a seeded code again breaks the chart's
 * unique (company, code).
 *
 * @mixin ApiTestCase
 */
trait ChartAccounts
{
    protected function chartAccount(Uuid $companyId, string $code, string $name = 'Cuenta de prueba', AccountNature $nature = AccountNature::Debit, ?string $parentCode = null): Account
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $seeded = $em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'code' => $code]);
        if ($seeded instanceof Account) {
            return $seeded;
        }

        $account = new Account($companyId, $code, $name, $nature, $parentCode, true);
        $this->save($account);

        return $account;
    }
}
