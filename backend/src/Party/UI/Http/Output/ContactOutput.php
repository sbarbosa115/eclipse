<?php

namespace App\Party\UI\Http\Output;

final readonly class ContactOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
