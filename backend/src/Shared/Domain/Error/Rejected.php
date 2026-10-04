<?php

namespace App\Shared\Domain\Error;

/**
 * The request cannot be honoured as it was asked — a link already used, an order with no way to reach the buyer —
 * and asking the same again will not help; it is not about one field of a form (InvalidValue) nor about the
 * record's state (Conflict).
 */
class Rejected extends DomainError
{
}
