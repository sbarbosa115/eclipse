<?php

namespace App\Party\Application\Command;

use App\Party\Application\Port\TerceroUsage;
use App\Party\Domain\Error\TerceroInUse;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeleteTerceroHandler implements CommandHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly TerceroUsage $usage,
    ) {
    }

    public function __invoke(DeleteTercero $command): void
    {
        $tercero = $this->terceros->get($command->companyId, $command->terceroId);
        if ($this->usage->isReferenced($command->companyId, $command->terceroId)) {
            throw new TerceroInUse();
        }
        $this->terceros->remove($tercero);
    }
}
