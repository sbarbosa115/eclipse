<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class InviteUserInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    /** billing or accountant: the owner is whoever signed the company up (and may make others owners later). */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['billing', 'accountant'], message: 'Invite a billing user or an accountant.')]
    public string $role = '';
}
