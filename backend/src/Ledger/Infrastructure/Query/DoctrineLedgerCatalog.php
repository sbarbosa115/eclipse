<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\AccountMatches;
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
use App\Ledger\Domain\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final class DoctrineLedgerCatalog implements LedgerCatalog
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
    ) {
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

        return self::accountView($account, $account->isPostable() && !$this->accounts->hasActiveChildren($companyId, $account->code()));
    }

    public function accountIdByCode(Uuid $companyId, string $code): ?Uuid
    {
        return $this->em->getRepository(Account::class)->findOneBy(['companyId' => $companyId, 'code' => $code])?->id();
    }

    public function searchAccounts(Uuid $companyId, string $query, bool $usableOnPurchasesOnly = false, int $limit = 50): AccountMatches
    {
        $matching = function () use ($companyId, $query, $usableOnPurchasesOnly): QueryBuilder {
            $qb = $this->em->createQueryBuilder()
                ->from(Account::class, 'a')
                ->where('a.companyId = :company')->setParameter('company', $companyId, 'uuid')
                ->andWhere('a.active = true')
                ->andWhere('a.level IN (:postable)')->setParameter('postable', ['subaccount', 'auxiliary'])
                // ledger_account_parent answers it: a subdivided account's movements go to its auxiliares.
                ->andWhere(\sprintf('NOT EXISTS (SELECT c.id FROM %s c WHERE c.companyId = a.companyId AND c.parentCode = a.code AND c.active = true)', Account::class));
            $words = preg_split('/\s+/u', trim($query), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $i => $word) {
                $like = addcslashes($word, '%_\\');
                ctype_digit($word)
                    ? $qb->andWhere("a.code LIKE :word$i")->setParameter("word$i", $like.'%')
                    : $qb->andWhere("a.name LIKE :word$i")->setParameter("word$i", '%'.$like.'%');
            }
            if ($usableOnPurchasesOnly) {
                $qb->andWhere('a.usableOnPurchases = true');
            }

            return $qb;
        };

        $total = (int) $matching()->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        /** @var list<Account> $accounts */
        $accounts = $matching()->select('a')->orderBy('a.code', 'ASC')->setMaxResults($limit)->getQuery()->getResult();

        // Every match is postable by the query itself.
        return new AccountMatches(array_map(static fn (Account $a) => self::accountView($a, true), $accounts), $total);
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

    /** $postable: PostableAccounts' rule, which needs the chart (an active auxiliar under it says no). */
    private static function accountView(Account $a, bool $postable): AccountView
    {
        return new AccountView($a->id()->toRfc4122(), $a->code(), $a->name(), $a->nature()->value, $a->level()->value, $a->parentCode(), $a->isStandard(), $a->isActive(), $postable, $a->isUsableOnPurchases());
    }
}
