<?php

namespace App\Access\Domain\Model;

enum UserStatus: string
{
    /** Invited by e-mail, no password yet. */
    case Invited = 'invited';
    case Active = 'active';
    /** May not sign in; kept because documents name who made them. */
    case Deactivated = 'deactivated';
}
