<?php

namespace App\Reporting\Application\Export;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Pdf = 'pdf';
}
