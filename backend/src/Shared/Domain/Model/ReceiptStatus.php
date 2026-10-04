<?php

namespace App\Shared\Domain\Model;

/**
 * A recibo de caja or de pago is emitted when saved (§4.9, §4.11) and may be voided at any time (§4.12).
 */
enum ReceiptStatus: string
{
    case Emitted = 'emitted';
    case Voided = 'voided';
}
