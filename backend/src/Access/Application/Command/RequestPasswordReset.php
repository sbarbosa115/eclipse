<?php

namespace App\Access\Application\Command;

/** Someone asks for a reset link for this e-mail. Whether it has an account is never told to them. */
final readonly class RequestPasswordReset
{
    public function __construct(public string $email)
    {
    }
}
