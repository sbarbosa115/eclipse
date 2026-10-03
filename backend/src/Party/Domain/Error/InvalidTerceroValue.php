<?php

namespace App\Party\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A value of a tercero that breaks a rule only the domain checks, reported against its field. */
final class InvalidTerceroValue extends InvalidValue
{
}
