<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeactivateTerceroHandler implements CommandHandler
{
    public function __construct(private readonly TerceroRepository $terceros)
    {
    }

    public function __invoke(DeactivateTercero $command): void
    {
        $this->terceros->get($command->companyId, $command->terceroId)->deactivate();
    }
}
