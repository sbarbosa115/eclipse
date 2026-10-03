<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $token = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 128, minMessage: 'The password must have at least {{ limit }} characters.')]
    public string $password = '';
}
