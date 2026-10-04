<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** The resolution's fields break a rule of §4.1 (range, dates, prefix, mode, what an issued number locks), all reported at once. */
final class InvalidResolution extends InvalidValues
{
}
