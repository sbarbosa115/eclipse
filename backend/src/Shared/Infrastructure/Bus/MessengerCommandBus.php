<?php

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Command\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The command bus on Symfony Messenger's `command.bus` (config/packages/messenger.yaml): synchronous — commands are
 * routed to no transport, so the handler runs inside dispatch() — and wrapped in a transaction by the
 * doctrine_transaction middleware, which flushes and commits on return and rolls back on any exception.
 */
final class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->bus->dispatch($command);
        } catch (WrappedExceptionsInterface $e) {
            // Messenger wraps whatever a handler threw — the command's (HandlerFailedException) or, after the commit,
            // an event's (DelayedMessageHandlingException around one): callers catch the error itself, as if they
            // had called the handler directly.
            throw self::unwrap($e);
        }

        /** @var list<HandledStamp> $handled */
        $handled = $envelope->all(HandledStamp::class);
        if (1 !== \count($handled)) {
            throw new \LogicException(\sprintf('%s must have exactly one handler, it has %d.', $command::class, \count($handled)));
        }

        return $handled[0]->getResult();
    }

    private static function unwrap(\Throwable $e): \Throwable
    {
        while ($e instanceof WrappedExceptionsInterface && [] !== $wrapped = $e->getWrappedExceptions()) {
            $e = $wrapped[array_key_first($wrapped)];
        }

        return $e;
    }
}
