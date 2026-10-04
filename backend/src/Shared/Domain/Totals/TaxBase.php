<?php

namespace App\Shared\Domain\Totals;

/**
 * What a percentage tax is a percentage of: the line's discounted subtotal (IVA, ReteFuente, ReteICA…), or the line's
 * own charge tax (ReteIVA, a percentage of the IVA, Art. 437-1 ET).
 */
enum TaxBase: string
{
    case Subtotal = 'subtotal';
    case ChargeTax = 'charge_tax';
}
