<?php

namespace App\Reporting\Application\Export;

final class ExportTooLarge extends \RuntimeException
{
    public function __construct(public readonly int $rows, public readonly int $limit)
    {
        parent::__construct(\sprintf('The report has %d rows; one export holds at most %d.', $rows, $limit));
    }
}
