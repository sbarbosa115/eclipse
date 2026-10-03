<?php

namespace App\Ledger\Domain\Model;

/**
 * Which side increases an account (its naturaleza in the PUC): débito for classes 1, 5, 6, 7; crédito for 2, 3, 4.
 * A few accounts run against their class (4175 Devoluciones en ventas is débito under the crédito class 4).
 */
enum AccountNature: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
