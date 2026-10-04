<?php

namespace App\Shared\Application\Event;

/**
 * Marks the handler of one event: a final class with `__invoke(TheEvent $event)`, registered on the command bus by
 * implementing this (services.yaml), like a CommandHandler.
 */
interface EventHandler
{
}
