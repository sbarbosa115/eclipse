<?php

namespace App\Company\Application\Profile;

use Symfony\Component\Uid\Uuid;

final readonly class RemoveLogo
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
    ) {
    }
}
