<?php

namespace App\Shared\Domain\Error;

/**
 * The server lacks something it needs to do this at all (a missing key in its configuration). Nothing the person can
 * change fixes it: whoever runs the server has to.
 */
abstract class ServiceUnavailable extends DomainError
{
}
