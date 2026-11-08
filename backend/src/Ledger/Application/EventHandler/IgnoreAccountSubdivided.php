<?php

namespace App\Ledger\Application\EventHandler;

use App\Ledger\Domain\Event\AccountSubdivided;
use App\Shared\Application\Event\EventHandler;

/**
 * Contract placeholder (item 0): the event bus refuses an event no handler takes. The "account-picker" item replaces
 * it with Catalog's and Party's handlers and deletes this one.
 */
final class IgnoreAccountSubdivided implements EventHandler
{
    public function __invoke(AccountSubdivided $event): void
    {
    }
}
