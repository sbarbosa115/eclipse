<?php

namespace App\Shared\Application\Command;

/**
 * Marks the handler of one command: a final class with `__invoke(TheCommand $command)`. Implementing this is all
 * the wiring it needs (services.yaml registers every one on the command bus), so the Application layer never names
 * the framework's attributes.
 */
interface CommandHandler
{
}
