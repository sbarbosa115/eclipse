<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangePostingRuleInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $accountId = '';
}
