<?php

namespace App\Sales\Domain\Model;

/**
 * §4.7: borrador → emitida → aceptada | rechazada | vencida, plus anulada.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Emitted = 'emitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Voided = 'voided';
}
