<?php

namespace App\Shared\UI\Http\Output;

use App\Shared\Application\Geo\DepartmentView;

final readonly class DepartmentOutput
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }

    public static function of(DepartmentView $v): self
    {
        return new self($v->code, $v->name);
    }
}
