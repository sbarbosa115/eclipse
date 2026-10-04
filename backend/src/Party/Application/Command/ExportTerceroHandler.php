<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Model\Tercero;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

/** Reads the personal data for the person who asked for it and leaves a trace in the audit log (Ley 1581). */
final class ExportTerceroHandler implements CommandHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(ExportTercero $command): Tercero
    {
        $tercero = $this->terceros->get($command->companyId, $command->terceroId);
        $this->audit->record($command->companyId, $command->userId, 'tercero.personal_data_exported', 'tercero', $tercero->id());

        return $tercero;
    }
}
