<?php

namespace App\Reporting\Application\Export;

final readonly class ReportColumn
{
    public function __construct(public string $label, public ColumnKind $kind = ColumnKind::Text)
    {
    }
}
