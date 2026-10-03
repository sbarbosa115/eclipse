<?php

namespace App\Party\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PhoneInput
{
    #[Assert\Length(max: 6)]
    public ?string $indicative = '57';

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $number = '';

    #[Assert\Length(max: 10)]
    public ?string $extension = null;
}
