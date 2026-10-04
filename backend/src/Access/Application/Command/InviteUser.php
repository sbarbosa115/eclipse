<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Model\Role;
use Symfony\Component\Uid\Uuid;

/** The owner invites someone by e-mail with a role (§4.14); "Invitar a tu contador" is this with the accountant role. */
final readonly class InviteUser
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invitedBy,
        public string $email,
        public Role $role,
    ) {
    }
}
