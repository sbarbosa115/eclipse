<?php

namespace App\Sales\Application\Command;

use App\Catalog\Application\Query\ProductCatalog;
use App\Company\Application\Numbering\SalesInvoiceNumbering;
use App\Ledger\Application\Posting\JournalPoster;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Posting\SalesInvoicePosting;
use App\Sales\Domain\Error\PeriodLocked;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Error\TerceroInactive;
use App\Sales\Domain\Event\SalesInvoiceEmailRequested;
use App\Sales\Domain\Repository\ReceivableRepository;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

/**
 * Emission (§4.8), in one transaction: the draft's own checks, an active client, an open period (AC-9), the
 * resolution's next number (AC-8: refused outside its dates or past hasta), a receivable per crédito line, and the
 * A.1 entry. Any refusal rolls everything back, numbers included.
 */
final class EmitSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly ReceivableRepository $receivables,
        private readonly TerceroDirectory $terceros,
        private readonly ProductCatalog $products,
        private readonly SalesInvoiceNumbering $numbering,
        private readonly JournalPoster $poster,
        private readonly EventBus $events,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(EmitSalesInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        $today = $this->calendar->today();
        $invoice->assertEmittable($today);

        $client = $this->terceros->get($command->companyId, $invoice->terceroId());
        if (!$client->active) {
            throw new TerceroInactive();
        }
        if ($command->send && null === $client->email) {
            throw new TerceroHasNoEmail();
        }
        if (!$this->poster->isOpen($command->companyId, $invoice->issueDate())) {
            throw new PeriodLocked($invoice->issueDate());
        }

        $number = $this->numbering->next($command->companyId, $invoice->issueDate());
        $receivables = $invoice->emit($number->resolutionId, $number->authorised->prefix, $number->authorised->sequence, $number->internal->sequence, $today, $command->userId, $this->calendar->now());
        foreach ($receivables as $receivable) {
            $this->receivables->add($receivable);
        }

        $entry = $this->poster->post(SalesInvoicePosting::entryFor($invoice, $this->revenueAccounts($command->companyId, $invoice->lines()), $command->userId));
        $invoice->recordPosting($entry);

        if ($command->send) {
            $this->events->publish(new SalesInvoiceEmailRequested($command->companyId->toRfc4122(), $invoice->id()->toRfc4122()));
        }
    }

    /**
     * Each product's own revenue account, where it has one (§4.3, §5: overridable per product).
     *
     * @param list<\App\Sales\Domain\Model\SalesInvoiceLine> $lines
     *
     * @return array<string, Uuid>
     */
    private function revenueAccounts(Uuid $companyId, array $lines): array
    {
        $accounts = [];
        foreach ($lines as $line) {
            $id = $line->productId();
            if (null === $id || \array_key_exists($id->toRfc4122(), $accounts)) {
                continue;
            }
            $account = $this->products->get($companyId, $id)->revenueAccountId;
            if (null !== $account) {
                $accounts[$id->toRfc4122()] = Uuid::fromString($account);
            }
        }

        return $accounts;
    }
}
