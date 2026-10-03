<?php

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Event\EventBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Events on the command bus itself, stamped to wait for the command being handled: Messenger's
 * dispatch_after_current_bus middleware runs before doctrine_transaction, so they are handled once the command's
 * transaction has committed. (Only the bus that is handling can hold a message back, hence the same bus.) Outside
 * any command, an event is handled at once.
 */
final class MessengerEventBus implements EventBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function publish(object $event): void
    {
        $this->bus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
    }
}
