<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A prefix or next number the series cannot take. */
final class InvalidNumberingSeries extends InvalidValue
{
}
