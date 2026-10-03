<?php

namespace App\Access\Application\Command;

/** The invitee, from the e-mailed link, chooses their name and password. */
final readonly class AcceptInvitation
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public string $name,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
