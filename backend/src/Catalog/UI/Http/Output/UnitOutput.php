<?php

namespace App\Catalog\UI\Http\Output;

use App\Catalog\Domain\Model\UnitOfMeasure;

/** An unidad de medida a product can use (the short DIAN list, §9 Q21). */
final readonly class UnitOutput
{
    public function __construct(
        /** DIAN code: 94, KGM, MTR, HUR, ZZ */
        public string $code,
        public string $name,
    ) {
    }

    public static function of(UnitOfMeasure $unit): self
    {
        return new self($unit->value, $unit->label());
    }
}
