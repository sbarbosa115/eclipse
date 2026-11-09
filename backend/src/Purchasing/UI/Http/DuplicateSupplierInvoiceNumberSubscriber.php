<?php

namespace App\Purchasing\UI\Http;

use App\Purchasing\Domain\Error\DuplicateSupplierInvoiceNumber;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A supplier's invoice number recorded twice is a 422 with its own code, `duplicate_supplier_invoice_number`, that
 * also carries a violation on `supplier_invoice_number`, so the form shows it next to the field. The same goes for the
 * database's unique key on the live number, which settles two saves that passed the handler's check at once.
 */
final class DuplicateSupplierInvoiceNumberSubscriber implements EventSubscriberInterface
{
    /** The unique key on (company, supplier, live number) of PurchaseInvoice. */
    private const UNIQUE_KEY = 'purchase_invoice_active_number';

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
        if ($e instanceof UniqueConstraintViolationException && str_contains($e->getMessage(), self::UNIQUE_KEY)) {
            // Two saves of the same number passed the check at once: the database's key settled it.
            $e = new DuplicateSupplierInvoiceNumber();
        }
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
