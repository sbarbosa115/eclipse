<?php

namespace App\Ledger\Domain\Model;

enum TaxClass: string
{
    /** Impuesto cargo: added to the subtotal (IVA, impoconsumo). */
    case Charge = 'charge';
    /** Impuesto retención: withheld from the total (ReteFuente, ReteIVA, ReteICA). */
    case Withholding = 'withholding';
}
