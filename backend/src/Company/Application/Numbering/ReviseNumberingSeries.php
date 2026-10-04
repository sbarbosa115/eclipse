<?php

namespace App\Company\Application\Numbering;

use Symfony\Component\Uid\Uuid;

/** The owner changes the prefix and the next number of one internal series. */
final readonly class ReviseNumberingSeries
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $kind,
        public string $prefix,
        public int $nextNumber,
    ) {
    }
}
