<?php

namespace App\Ledger\Application\PaymentMethod;

use App\Ledger\Domain\Model\PaymentMethod;

/** What the audit log keeps of a payment method. */
final class PaymentMethodSnapshots
{
    /**
     * @return array<string, mixed>
     */
    public static function of(PaymentMethod $method): array
    {
        return ['name' => $method->name(), 'kind' => $method->kind()->value, 'account_id' => $method->accountId()?->toRfc4122()];
    }
}
