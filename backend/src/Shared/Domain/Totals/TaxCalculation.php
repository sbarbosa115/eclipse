<?php

namespace App\Shared\Domain\Totals;

enum TaxCalculation: string
{
    case Percentage = 'percentage';
    case PerUnit = 'per_unit';
}
