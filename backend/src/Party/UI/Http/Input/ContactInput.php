<?php

namespace App\Party\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactInput
{
    /** Present when the contact already exists, so documents keep pointing at it. */
    #[Assert\Uuid]
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public string $name = '';

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    public ?string $phone = null;
}
