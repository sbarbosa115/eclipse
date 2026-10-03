<?php

namespace App\Ledger\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Whether anything points at a tax or a payment method: a document's line or forma de pago, a receipt or payment, a
 * product's default, the company's defaults. Such a tax or method can be deactivated, never deleted (§4.4, §4.5).
 * Documents belong to other contexts, so the check is a query, not a model rule.
 */
interface CatalogUsage
{
    public function taxIsUsed(Uuid $companyId, Uuid $taxId): bool;

    public function paymentMethodIsUsed(Uuid $companyId, Uuid $paymentMethodId): bool;

    /** @return list<string> ids (RFC 4122) of the company's taxes something points at */
    public function usedTaxIds(Uuid $companyId): array;

    /** @return list<string> ids (RFC 4122) of the company's payment methods something points at */
    public function usedPaymentMethodIds(Uuid $companyId): array;
}
