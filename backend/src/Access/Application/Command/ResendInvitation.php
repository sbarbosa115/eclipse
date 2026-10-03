<?php

namespace App\Access\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new invitation e-mail with a new link; the earlier link stops working. */
final readonly class ResendInvitation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $resentBy,
        public Uuid $userId,
    ) {
    }
}
