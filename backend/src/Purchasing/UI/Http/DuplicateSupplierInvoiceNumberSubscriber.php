<?php

namespace App\Purchasing\UI\Http;

use App\Purchasing\Domain\Error\DuplicateSupplierInvoiceNumber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A supplier's invoice number recorded twice is a 422 with its own code, `duplicate_supplier_invoice_number`, that
 * also carries a violation on `supplier_invoice_number`, so the form shows it next to the field.
 */
final class DuplicateSupplierInvoiceNumberSubscriber implements EventSubscriberInterface
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
        if (!$e instanceof DuplicateSupplierInvoiceNumber) {
            return;
        }

        $message = $this->translator->trans($e->getMessage(), [], 'validators');
        $event->setResponse(new JsonResponse([
            'error' => $e->errorCode(),
            'message' => $message,
            'violations' => [['field' => DuplicateSupplierInvoiceNumber::FIELD, 'message' => $message]],
        ], 422));
        $event->stopPropagation();
    }
}
