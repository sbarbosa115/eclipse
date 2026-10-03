<?php

namespace App\Access\UI\Http\Output;

/** What the invitation page shows before the invitee accepts. */
final readonly class InvitationOutput
{
    public function __construct(
        public string $email,
        public string $companyName,
        /** billing or accountant */
        public string $role,
    ) {
    }
}
