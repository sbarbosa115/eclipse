<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/**
 * What other contexts read from the ledger's catalogs: taxes and payment methods to copy onto documents, accounts to
 * check and show. Every method is scoped to one company; another company's id is "not found".
 */
interface LedgerCatalog
{
    /** @throws \App\Shared\Domain\Error\NotFound tax_not_found */
    public function tax(Uuid $companyId, Uuid $taxId): TaxView;

    /** @return list<TaxView> active first, by class then name */
    public function taxes(Uuid $companyId, ?string $taxClass = null, bool $activeOnly = true): array;

    /** @throws \App\Shared\Domain\Error\NotFound payment_method_not_found */
    public function paymentMethod(Uuid $companyId, Uuid $paymentMethodId): PaymentMethodView;

    /** @return list<PaymentMethodView> */
    public function paymentMethods(Uuid $companyId, bool $activeOnly = true): array;

    /** @throws \App\Shared\Domain\Error\NotFound account_not_found */
    public function account(Uuid $companyId, Uuid $accountId): AccountView;

    /**
     * Postable accounts matching a code prefix or part of the name, for pickers.
     *
     * @return list<AccountView>
     */
    public function searchAccounts(Uuid $companyId, string $query, bool $usableOnPurchasesOnly = false, int $limit = 20): array;
}
