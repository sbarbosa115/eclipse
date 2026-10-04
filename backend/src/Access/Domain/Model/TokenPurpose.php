<?php

namespace App\Access\Domain\Model;

enum TokenPurpose: string
{
    case PasswordReset = 'password_reset';
    case Invitation = 'invitation';
}
