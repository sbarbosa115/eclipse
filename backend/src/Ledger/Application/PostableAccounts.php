<?php

namespace App\Ledger\Application;

use App\Ledger\Application\Query\AccountView;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Error\AccountHasAuxiliaries;
use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Ledger\Domain\Error\InvalidPaymentMethod;
use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Repository\AccountRepository;
use Symfony\Component\Uid\Uuid;

/**
 * The one rule of where entries post (observations PRD §4.1): an account of the company's chart that is active, a
 * subcuenta or an auxiliar, and has no active child (a subdivided account's movements go to one of its auxiliares).
 * The poster, the posting rules and the tax and payment-method forms ask it; the catalog's AccountView::$postable
 * answers the same for the other contexts (products, terceros, purchase lines) and the pickers.
 */
final class PostableAccounts
{
    public function __construct(
        private readonly LedgerCatalog $catalog,
        private readonly AccountRepository $accounts,
    ) {
    }

    public function isPostable(Account $account): bool
    {
        return $account->isPostable() && !$this->accounts->hasActiveChildren($account->companyId(), $account->code());
    }

    /**
     * @throws AccountNotPostable    a class, group or cuenta, or an inactive account
     * @throws AccountHasAuxiliaries a subcuenta or auxiliar with active auxiliares
     */
    public function assertPostable(Account $account): void
    {
        if (!$account->isPostable()) {
            throw new AccountNotPostable($account->code());
        }
        if ($this->accounts->hasActiveChildren($account->companyId(), $account->code())) {
            throw new AccountHasAuxiliaries($account->code());
        }
    }

    /**
     * @throws InvalidTaxDefinition on $field
     */
    public function forTax(Uuid $companyId, ?string $accountId, string $field): ?Uuid
    {
        return $this->check($companyId, $accountId, static fn (string $message) => new InvalidTaxDefinition($field, $message));
    }

    /**
     * @throws InvalidPaymentMethod on account_id
     */
    public function forPaymentMethod(Uuid $companyId, ?string $accountId): ?Uuid
    {
        return $this->check($companyId, $accountId, static fn (string $message) => new InvalidPaymentMethod('account_id', $message));
    }

    /**
     * @param callable(string): \Throwable $refuse
     */
    private function check(Uuid $companyId, ?string $accountId, callable $refuse): ?Uuid
    {
        if (null === $accountId || '' === $accountId) {
            return null;
        }
        if (!Uuid::isValid($accountId)) {
            throw $refuse('This account does not exist.');
        }
        $id = Uuid::fromString($accountId);
        try {
            $account = $this->catalog->account($companyId, $id);
        } catch (AccountNotFound) {
            throw $refuse('This account does not exist.');
        }
        if (!$account->postable) {
            throw $refuse(self::subdivided($account) ? 'This account has auxiliaries: choose one of them.' : 'Choose a sub-account or an auxiliary account that is active.');
        }

        return $id;
    }

    /** Active and of a level that posts, yet not postable: it has active auxiliares. */
    private static function subdivided(AccountView $account): bool
    {
        return $account->active && \in_array($account->level, ['subaccount', 'auxiliary'], true);
    }
}
