<?php

namespace App\Shared\Application\Geo;

final readonly class MunicipalityView
{
    public function __construct(
        public string $code,
        public string $name,
        public string $departmentCode,
        public string $departmentName,
    ) {
    }
}
