<?php

namespace App\Shared\Application\Event;

/**
 * What a command handler announces for later: an email to send, a file nobody points at any more. Each event is
 * handled after the command's transaction commits — never for a command that failed, and never before what it
 * announces is saved — by the EventHandler for its class, in a transaction of its own.
 *
 * A handler that fails after the commit does not undo the command: the caller still gets the error, but what the
 * command saved stays saved.
 */
interface EventBus
{
    public function publish(object $event): void;
}
