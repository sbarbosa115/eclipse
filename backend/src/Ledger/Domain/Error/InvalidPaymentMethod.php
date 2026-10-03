<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A payment method's name or account breaks a rule of the catalog; reported against that field. */
final class InvalidPaymentMethod extends InvalidValue
{
}
