<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** The company's profile breaks a rule only the domain knows (a NIT already registered, a default tax of the wrong class). */
final class InvalidCompanyProfile extends InvalidValues
{
}
