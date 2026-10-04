<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangeRoleInput
{
    /** owner, billing or accountant */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['owner', 'billing', 'accountant'], message: 'Choose a valid role.')]
    public string $role = '';
}
