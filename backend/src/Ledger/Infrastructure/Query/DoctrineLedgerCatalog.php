<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\AccountView;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\PaymentMethodView;
use App\Ledger\Application\Query\TaxView;
use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Error\PaymentMethodNotFound;
use App\Ledger\Domain\Error\TaxNotFound;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\PaymentMethod;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineLedgerCatalog implements LedgerCatalog
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function tax(Uuid $companyId, Uuid $taxId): TaxView
    {
        $tax = $this->em->getRepository(Tax::class)->findOneBy(['companyId' => $companyId, 'id' => $taxId]) ?? throw new TaxNotFound();

        return self::taxView($tax);
    }

    public function taxes(Uuid $companyId, ?string $taxClass = null, bool $activeOnly = true): array
    {
        $criteria = ['companyId' => $companyId];
        if (null !== $taxClass) {
            $criteria['taxClass'] = TaxClass::from($taxClass);
        }
        if ($activeOnly) {
            $criteria['active'] = true;
        }

        return array_map(self::taxView(...), $this->em->getRepository(Tax::class)->findBy($criteria, ['taxClass' => 'ASC', 'name' => 'ASC']));
    }

    public function paymentMethod(Uuid $companyId, Uuid $paymentMethodId): PaymentMethodView
    {
        $method = $this->em->getRepository(PaymentMethod::class)->findOneBy(['companyId' => $companyId, 'id' => $paymentMethodId]) ?? throw new PaymentMethodNotFound();

        return $this->paymentMethodView($method);
    }

    public function paymentMethods(Uuid $companyId, bool $activeOnly = true): array
    {
        $criteria = ['companyId' => $companyId] + ($activeOnly ? ['active' => true] : []);

        return array_map($this->paymentMethodView(...), $this->em->getRepository(PaymentMethod::class)->findBy($criteria, ['name' => 'ASC']));
    }

    public function account(Uuid $companyId, Uuid $accountId): AccountView
    {
        $account = $this->em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'id' => $accountId]) ?? throw new AccountNotFound();

        return self::accountView($account);
    }

    public function accountIdByCode(Uuid $companyId, string $code): ?Uuid
    {
        return $this->em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'code' => $code])?->id();
    }

    public function searchAccounts(Uuid $companyId, string $query, bool $usableOnPurchasesOnly = false, int $limit = 20): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')->from(Account::class, 'a')
            ->where('a.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->andWhere('a.active = true')
            ->andWhere('a.level IN (:postable)')->setParameter('postable', ['subaccount', 'auxiliary'])
            ->orderBy('a.code', 'ASC')->setMaxResults($limit);
        $query = trim($query);
        if ('' !== $query) {
            $like = addcslashes($query, '%_\\');
            $qb->andWhere('a.code LIKE :prefix OR a.name LIKE :name')
                ->setParameter('prefix', $like.'%')->setParameter('name', '%'.$like.'%');
        }
        if ($usableOnPurchasesOnly) {
            $qb->andWhere('a.usableOnPurchases = true');
        }

        /** @var list<Account> $accounts */
        $accounts = $qb->getQuery()->getResult();

        return array_map(self::accountView(...), $accounts);
    }

    private static function taxView(Tax $t): TaxView
    {
        return new TaxView(
            $t->id()->toRfc4122(),
            $t->name(),
            $t->taxClass()->value,
            $t->kind()->value,
            $t->calculation()->value,
            $t->rate(),
            $t->salesAccountId()?->toRfc4122(),
            $t->purchaseAccountId()?->toRfc4122(),
            $t->validFrom()?->format('Y-m-d'),
            $t->validTo()?->format('Y-m-d'),
            $t->isActive(),
            $t->isStandard(),
        );
    }

    private function paymentMethodView(PaymentMethod $m): PaymentMethodView
    {
        $account = null === $m->accountId() ? null : $this->em->getRepository(Account::class)->findOneBy(['companyId' => $m->companyId(), 'id' => $m->accountId()]);

        return new PaymentMethodView($m->id()->toRfc4122(), $m->name(), $m->kind()->value, $m->accountId()?->toRfc4122(), $account?->code(), $account?->name(), $m->isActive(), $m->isStandard());
    }

    private static function accountView(Account $a): AccountView
    {
        return new AccountView($a->id()->toRfc4122(), $a->code(), $a->name(), $a->nature()->value, $a->level()->value, $a->parentCode(), $a->isStandard(), $a->isActive(), $a->isPostable(), $a->isUsableOnPurchases());
    }
}
