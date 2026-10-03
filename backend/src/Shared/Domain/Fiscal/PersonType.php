<?php

namespace App\Shared\Domain\Fiscal;

enum PersonType: string
{
    case Person = 'persona';
    case Company = 'empresa';
}
