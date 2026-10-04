<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

final class RecordSignInHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RecordSignIn $command): void
    {
        $this->users->get($command->companyId, $command->userId)->signedIn($this->clock->now());
    }
}
