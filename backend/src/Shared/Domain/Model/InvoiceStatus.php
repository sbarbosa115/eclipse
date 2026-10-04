<?php

namespace App\Shared\Domain\Model;

/**
 * The life of a factura de venta or de compra (§4.6): borrador → emitido → pagado parcialmente → pagado, and anulado
 * from any emitted state while nothing is allocated to it.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Emitted = 'emitted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    public function isEditable(): bool
    {
        return self::Draft === $this;
    }
}
