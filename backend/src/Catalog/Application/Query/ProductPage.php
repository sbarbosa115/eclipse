<?php

namespace App\Catalog\Application\Query;

final readonly class ProductPage
{
    /**
     * @param list<ProductView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
