<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class CreateCategory
{
    public function __construct(
        public Uuid $companyId,
        public string $name,
    ) {
    }
}
