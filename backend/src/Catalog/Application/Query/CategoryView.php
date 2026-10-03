<?php

namespace App\Catalog\Application\Query;

final readonly class CategoryView
{
    public function __construct(
        public string $id,
        public string $name,
        /** How many products and services are in it. */
        public int $productCount,
    ) {
    }
}
