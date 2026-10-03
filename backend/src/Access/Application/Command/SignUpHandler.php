<?php

namespace App\Access\Application\Command;

use App\Access\Application\Port\PasswordHasher;
use App\Access\Domain\Error\EmailTaken;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use App\Company\Application\CompanyRegistration;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

final class SignUpHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CompanyRegistration $companies,
        private readonly PasswordHasher $hasher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Uuid the owner's user id
     */
    public function __invoke(SignUp $command): Uuid
    {
        if (null !== $this->users->findByEmail($command->email)) {
            throw new EmailTaken();
        }

        $now = $this->clock->now();
        $companyId = $this->companies->register($command->companyName, $command->nit, $now);
        $owner = User::owner($companyId, $command->email, trim($command->ownerName), $this->hasher->hash($command->password), $now);
        $this->users->add($owner);

        return $owner->id();
    }
}
