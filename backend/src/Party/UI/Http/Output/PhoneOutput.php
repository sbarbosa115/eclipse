<?php

namespace App\Party\UI\Http\Output;

final readonly class PhoneOutput
{
    public function __construct(
        public string $indicative,
        public string $number,
        public ?string $extension,
    ) {
    }
}
