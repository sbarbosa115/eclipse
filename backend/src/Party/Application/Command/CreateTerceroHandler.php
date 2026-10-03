<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Error\DuplicateIdentification;
use App\Party\Domain\Model\Tercero;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

final class CreateTerceroHandler implements CommandHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly TerceroAccounts $accounts,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateTercero $command): Uuid
    {
        $p = $command->profile;
        if (null !== $this->terceros->findByIdentification($command->companyId, $p->identificationType->value, $p->identificationNumber, $p->branchCode)) {
            throw new DuplicateIdentification();
        }
        $this->accounts->check($command->companyId, $p);

        $tercero = Tercero::register($command->companyId, $p, $this->clock->now());
        $this->terceros->add($tercero);

        return $tercero->id();
    }
}
