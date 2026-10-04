<?php

namespace App\Catalog\UI\Http\Output;

use App\Catalog\Application\Query\CategoryView;

final readonly class CategoryOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public int $productCount,
    ) {
    }

    public static function of(CategoryView $v): self
    {
        return new self($v->id, $v->name, $v->productCount);
    }
}
