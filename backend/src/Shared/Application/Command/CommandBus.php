<?php

namespace App\Shared\Application\Command;

/**
 * Runs one write use case, synchronously, in one database transaction: the handler's changes are committed when it
 * returns and rolled back when it throws. Nothing is queued — the caller gets the handler's result (or its
 * DomainError, unwrapped) before it answers the request.
 *
 * Controllers dispatch commands; handlers never flush. Reads do not come through here: a controller calls a query
 * service directly.
 */
interface CommandBus
{
    /**
     * @param object $command a readonly DTO named after the intent (RegisterCustomer, PlaceOrder)
     *
     * @return mixed what the handler returns — usually the id of what it created or changed, or nothing
     */
    public function dispatch(object $command): mixed;
}
