<?php

namespace App\Company\Application\Profile;

use Symfony\Component\Uid\Uuid;

/** The owner uploads the logo: the file at $path was already checked as a PNG or JPEG of at most 2 MB. */
final readonly class ReplaceLogo
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $path,
        public string $originalName,
        public string $contentType,
    ) {
    }
}
