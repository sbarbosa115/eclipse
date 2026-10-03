<?php

namespace App\Shared\Domain\Model;

/**
 * A payment method is contado (the money moves now, to its account) or crédito (it creates a receivable or payable
 * with a due date), §4.5.
 */
enum PaymentKind: string
{
    case Cash = 'cash';
    case Credit = 'credit';
}
