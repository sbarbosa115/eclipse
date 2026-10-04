<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

final class EraseTerceroHandler implements CommandHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(EraseTercero $command): void
    {
        $tercero = $this->terceros->get($command->companyId, $command->terceroId);
        $wasErased = null !== $tercero->erasedAt();
        $tercero->erase($this->clock->now());
        if (!$wasErased) {
            $this->audit->record($command->companyId, $command->userId, 'tercero.personal_data_erased', 'tercero', $tercero->id());
        }
    }
}
