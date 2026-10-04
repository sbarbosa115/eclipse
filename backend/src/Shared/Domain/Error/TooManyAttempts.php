<?php

namespace App\Shared\Domain\Error;

/**
 * Asked too often in too short a time (chat messages, Maps imports): try again later.
 */
class TooManyAttempts extends DomainError
{
}
