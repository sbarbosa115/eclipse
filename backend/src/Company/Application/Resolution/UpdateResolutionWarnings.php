<?php

namespace App\Company\Application\Resolution;

use Symfony\Component\Uid\Uuid;

/** The owner chooses when to be warned: fewer numbers or fewer days left than these. */
final readonly class UpdateResolutionWarnings
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public int $numbers,
        public int $days,
    ) {
    }
}
