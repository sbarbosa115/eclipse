<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Error\DuplicateIdentification;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdateTerceroHandler implements CommandHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly TerceroAccounts $accounts,
    ) {
    }

    public function __invoke(UpdateTercero $command): void
    {
        $tercero = $this->terceros->get($command->companyId, $command->terceroId);
        $p = $command->profile;
        $other = $this->terceros->findByIdentification($command->companyId, $p->identificationType->value, $p->identificationNumber, $p->branchCode);
        if (null !== $other && !$other->id()->equals($tercero->id())) {
            throw new DuplicateIdentification();
        }
        $this->accounts->check($command->companyId, $p);

        $tercero->revise($p);
    }
}
