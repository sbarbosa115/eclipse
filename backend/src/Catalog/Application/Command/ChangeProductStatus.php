<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Deactivate (out of new documents, history kept) or reactivate a product. */
final readonly class ChangeProductStatus
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $productId,
        public bool $active,
    ) {
    }
}
