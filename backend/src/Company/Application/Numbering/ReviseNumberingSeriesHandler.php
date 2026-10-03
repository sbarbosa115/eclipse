<?php

namespace App\Company\Application\Numbering;

use App\Company\Application\Port\CompanyAudit;
use App\Company\Domain\Error\NumberingSeriesNotFound;
use App\Company\Domain\Model\SeriesKind;
use App\Company\Domain\Repository\NumberingSeriesRepository;
use App\Shared\Application\Command\CommandHandler;

final class ReviseNumberingSeriesHandler implements CommandHandler
{
    public function __construct(
        private readonly NumberingSeriesRepository $series,
        private readonly CompanyAudit $audit,
    ) {
    }

    public function __invoke(ReviseNumberingSeries $command): void
    {
        $kind = SeriesKind::tryFrom($command->kind);
        if (null === $kind || !$kind->isEditable()) {
            throw new NumberingSeriesNotFound();
        }
        // Locked, like an emission taking a number: an edit and a document never read the same number.
        $series = $this->series->lock($command->companyId, $kind);
        $before = ['prefix' => $series->prefix(), 'next_number' => $series->nextNumber()];
        $series->revise($command->prefix, $command->nextNumber);
        $this->audit->record($command->companyId, $command->userId, 'numbering_series.updated', 'numbering_series', $series->id(), ['kind' => $kind->value, 'from' => $before, 'to' => ['prefix' => $series->prefix(), 'next_number' => $series->nextNumber()]]);
    }
}
