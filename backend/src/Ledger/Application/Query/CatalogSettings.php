<?php

namespace App\Ledger\Application\Query;

use App\Ledger\Application\Port\CatalogUsage;
use App\Ledger\Domain\Error\AccountNotFound;
use Symfony\Component\Uid\Uuid;

/**
 * What the Impuestos and Formas de pago tabs read: every tax and payment method of the company, inactive ones too,
 * with what the catalog's own views leave out (the accounts' names, whether a document uses it).
 */
final class CatalogSettings
{
    public function __construct(
        private readonly LedgerCatalog $catalog,
        private readonly CatalogUsage $usage,
    ) {
    }

    /** @return list<TaxSettingView> */
    public function taxes(Uuid $companyId): array
    {
        $used = array_flip($this->usage->usedTaxIds($companyId));
        $accounts = [];
        $views = [];
        foreach ($this->catalog->taxes($companyId, null, false) as $tax) {
            $views[] = new TaxSettingView($tax, $this->account($companyId, $tax->salesAccountId, $accounts), $this->account($companyId, $tax->purchaseAccountId, $accounts), isset($used[$tax->id]));
        }

        return $views;
    }

    public function tax(Uuid $companyId, Uuid $taxId): TaxSettingView
    {
        $tax = $this->catalog->tax($companyId, $taxId);
        $accounts = [];

        return new TaxSettingView($tax, $this->account($companyId, $tax->salesAccountId, $accounts), $this->account($companyId, $tax->purchaseAccountId, $accounts), $this->usage->taxIsUsed($companyId, $taxId));
    }

    /** @return list<PaymentMethodSettingView> */
    public function paymentMethods(Uuid $companyId): array
    {
        $used = array_flip($this->usage->usedPaymentMethodIds($companyId));

        return array_map(static fn (PaymentMethodView $m) => new PaymentMethodSettingView($m, isset($used[$m->id])), $this->catalog->paymentMethods($companyId, false));
    }

    public function paymentMethod(Uuid $companyId, Uuid $methodId): PaymentMethodSettingView
    {
        return new PaymentMethodSettingView($this->catalog->paymentMethod($companyId, $methodId), $this->usage->paymentMethodIsUsed($companyId, $methodId));
    }

    /**
     * @param array<string, ?AccountView> $known accounts already read, by id
     */
    private function account(Uuid $companyId, ?string $id, array &$known): ?AccountView
    {
        if (null === $id) {
            return null;
        }
        if (!\array_key_exists($id, $known)) {
            try {
                $known[$id] = $this->catalog->account($companyId, Uuid::fromString($id));
            } catch (AccountNotFound) {
                $known[$id] = null;
            }
        }

        return $known[$id];
    }
}
