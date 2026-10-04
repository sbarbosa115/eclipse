<?php

namespace App\Access\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RecordSignIn
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
    ) {
    }
}
