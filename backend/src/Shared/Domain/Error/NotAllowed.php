<?php

namespace App\Shared\Domain\Error;

/**
 * Whoever asked may not do this here, whatever the record's state.
 */
abstract class NotAllowed extends DomainError
{
}
