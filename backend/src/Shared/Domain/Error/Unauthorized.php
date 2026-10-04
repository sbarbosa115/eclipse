<?php

namespace App\Shared\Domain\Error;

/**
 * The credential that stands in for a login — a magic-link token, a claim token — is missing, wrong or expired.
 */
abstract class Unauthorized extends DomainError
{
}
