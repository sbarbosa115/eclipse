<?php

namespace App\Shared\Domain\Error;

/**
 * The record does not exist for whoever asked.
 */
class NotFound extends DomainError
{
    public static function record(): self
    {
        return new self('not_found', 'Not found.');
    }
}
