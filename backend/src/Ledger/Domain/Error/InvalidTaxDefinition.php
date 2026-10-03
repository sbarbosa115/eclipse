<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A tax's rate, kind, calculation or dates break a rule of the catalog; reported against that field. */
final class InvalidTaxDefinition extends InvalidValue
{
}
