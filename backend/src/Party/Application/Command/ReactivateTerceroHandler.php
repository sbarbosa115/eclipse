<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Command\CommandHandler;

final class ReactivateTerceroHandler implements CommandHandler
{
    public function __construct(private readonly TerceroRepository $terceros)
    {
    }

    public function __invoke(ReactivateTercero $command): void
    {
        $this->terceros->get($command->companyId, $command->terceroId)->reactivate();
    }
}
