<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** The token of an e-mailed link (the part after `#`), in the body so it never lands in a URL. */
final class TokenInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $token = '';
}
