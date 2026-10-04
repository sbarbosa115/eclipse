<?php

namespace App\Party\Domain\Model;

use Symfony\Component\Uid\Uuid;

/** A contact as the person submitted it: with its id when it already exists, so documents keep pointing at it. */
final readonly class ContactDraft
{
    public function __construct(
        public ?Uuid $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
