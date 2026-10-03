<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/**
 * An invitation or password-reset link that does not work: unknown, already used, replaced by a newer one, expired,
 * or sent for the other purpose. All answer alike, so a guessed token learns nothing.
 */
final class LinkInvalid extends NotFound
{
    public function __construct()
    {
        parent::__construct('link_invalid', 'This link has expired or was already used.');
    }
}
