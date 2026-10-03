<?php

namespace App\Sales\Application\Command;

use App\Sales\Application\SalesCalendar;
use App\Sales\Domain\Model\InvoiceLineDraft;
use App\Sales\Domain\Model\InvoicePaymentDraft;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Model\SalesInvoiceLine;
use App\Sales\Domain\Model\SalesInvoicePayment;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use Symfony\Component\Uid\Uuid;

/**
 * "Duplicar" (§4.15): a new draft dated today with the same client, contact, lines (taxes as they were copied) and
 * formas de pago; a crédito keeps its term (the same number of days after the new date).
 */
final class DuplicateSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly SalesCalendar $calendar,
    ) {
    }

    /** @return Uuid the new draft's id */
    public function __invoke(DuplicateSalesInvoice $command): Uuid
    {
        $source = $this->invoices->get($command->companyId, $command->invoiceId);
        $today = $this->calendar->today();

        $copy = new SalesInvoice($command->companyId, $source->terceroId(), $source->terceroName(), $today, $command->userId, $this->calendar->now());
        $copy->revise($source->terceroId(), $source->terceroName(), $source->contactId(), $source->sellerId(), $today, $source->notes());
        $copy->replaceLines(array_map(static fn (SalesInvoiceLine $l) => new InvoiceLineDraft($l->productId(), $l->description(), $l->quantity(), $l->unitPrice(), $l->discount(), $l->chargeTax(), $l->withholdingTax()), $source->lines()));
        $copy->replacePayments(array_map(static function (SalesInvoicePayment $p) use ($source, $today): InvoicePaymentDraft {
            $due = $p->dueDate();
            if (null !== $due) {
                $days = (int) $source->issueDate()->diff($due)->format('%r%a');
                $due = $today->modify(\sprintf('%+d days', max(0, $days)));
            }

            return new InvoicePaymentDraft($p->paymentMethodId(), $p->methodName(), $p->kind(), $p->accountId(), $p->amount(), $due);
        }, $source->payments()));
        $this->invoices->add($copy);

        return $copy->id();
    }
}
