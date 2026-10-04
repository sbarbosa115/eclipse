<?php

namespace App\Reporting\Application\Dashboard;

final readonly class MonthTotal
{
    public function __construct(public string $amount, public int $count)
    {
    }
}
