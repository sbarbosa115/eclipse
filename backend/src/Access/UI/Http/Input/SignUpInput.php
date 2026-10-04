<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class SignUpInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public string $companyName = '';

    /** The company's NIT, without the DV (it is computed); dots and dashes are ignored. */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[\d.\- ]{5,20}$/', message: 'Write the NIT with digits only, without the verification digit.')]
    public string $nit = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $ownerName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 128, minMessage: 'The password must have at least {{ limit }} characters.')]
    public string $password = '';
}
