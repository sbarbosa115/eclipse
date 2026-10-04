<?php

namespace App\Shared\Domain\Error;

/**
 * A service outside the app (the mail server, OpenAI, Apify) failed. What the command saved stays saved;
 * trying again later may work.
 */
class ExternalServiceFailed extends DomainError
{
}
