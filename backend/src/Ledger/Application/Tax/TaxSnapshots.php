<?php

namespace App\Ledger\Application\Tax;

use App\Ledger\Domain\Model\Tax;

/** What the audit log keeps of a tax. */
final class TaxSnapshots
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Tax $tax): array
    {
        return [
            'name' => $tax->name(),
            'calculation' => $tax->calculation()->value,
            'rate' => $tax->rate(),
            'sales_account_id' => $tax->salesAccountId()?->toRfc4122(),
            'purchase_account_id' => $tax->purchaseAccountId()?->toRfc4122(),
            'valid_from' => $tax->validFrom()?->format('Y-m-d'),
            'valid_to' => $tax->validTo()?->format('Y-m-d'),
        ];
    }
}
