<?php

namespace App\Party\UI\Http;

use App\Party\Domain\Error\DuplicateIdentification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A repeated identification is a 422 with its own code, `duplicate_identification`, that also carries a violation on
 * `identification_number`, so a form shows it next to the field like any validation error.
 */
final class DuplicateIdentificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof DuplicateIdentification) {
            return;
        }

        $message = $this->translator->trans($e->getMessage(), [], 'validators');
        $event->setResponse(new JsonResponse([
            'error' => $e->errorCode(),
            'message' => $message,
            'violations' => [['field' => DuplicateIdentification::FIELD, 'message' => $message]],
        ], 422));
        $event->stopPropagation();
    }
}
