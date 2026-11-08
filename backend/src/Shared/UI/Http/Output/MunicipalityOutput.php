<?php

namespace App\Shared\UI\Http\Output;

use App\Shared\Application\Geo\MunicipalityView;

final readonly class MunicipalityOutput
{
    public function __construct(
        public string $code,
        public string $name,
        public string $departmentCode,
        public string $departmentName,
    ) {
    }

    public static function of(MunicipalityView $v): self
    {
        return new self($v->code, $v->name, $v->departmentCode, $v->departmentName);
    }
}
