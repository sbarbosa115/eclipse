<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** Rows of the exógena concept table that break a rule of the whole list (a prefix twice in one format), by field. */
final class InvalidExogenaConcepts extends InvalidValues
{
}
