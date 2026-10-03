<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteProduct
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $productId,
    ) {
    }
}
