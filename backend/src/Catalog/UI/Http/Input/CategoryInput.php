<?php

namespace App\Catalog\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CategoryInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';
}
