<?php

namespace App\Shared\Application\Geo;

final readonly class DepartmentView
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
