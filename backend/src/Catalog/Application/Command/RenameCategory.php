<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RenameCategory
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $categoryId,
        public string $name,
    ) {
    }
}
