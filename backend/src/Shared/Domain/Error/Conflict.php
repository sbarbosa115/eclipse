<?php

namespace App\Shared\Domain\Error;

/**
 * The request is fine, but the record's current state refuses it: a slug already taken, a prospect already claimed.
 * Trying again later, after something else changes, may work.
 */
abstract class Conflict extends DomainError
{
}
