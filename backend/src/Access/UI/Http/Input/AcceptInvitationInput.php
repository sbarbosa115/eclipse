<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AcceptInvitationInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $token = '';

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 128, minMessage: 'The password must have at least {{ limit }} characters.')]
    public string $password = '';
}
