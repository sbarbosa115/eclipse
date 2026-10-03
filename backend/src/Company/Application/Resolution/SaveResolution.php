<?php

namespace App\Company\Application\Resolution;

use Symfony\Component\Uid\Uuid;

/** The owner creates the company's invoicing resolution or edits it ($create tells which). Dates are "Y-m-d". */
final readonly class SaveResolution
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public bool $create,
        public string $resolutionNumber,
        public string $prefix,
        public int $rangeFrom,
        public int $rangeTo,
        public string $validFrom,
        public string $validTo,
        public string $mode,
    ) {
    }
}
