<?php

namespace App\Shared\Domain\Error;

/**
 * The request was understood and well formed, and still cannot be done, for a reason with its own code the UI
 * explains: an order naming products the business does not sell. Unlike InvalidValue it is not
 * about one field of the form.
 */
abstract class Refused extends DomainError
{
}
