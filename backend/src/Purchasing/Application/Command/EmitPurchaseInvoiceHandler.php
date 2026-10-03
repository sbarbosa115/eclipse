<?php

namespace App\Purchasing\Application\Command;

use App\Catalog\Application\Query\ProductCatalog;
use App\Company\Application\Numbering\Numbering;
use App\Ledger\Application\Posting\JournalPoster;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Application\ColombianCalendar;
use App\Purchasing\Application\Posting\ProductAccounting;
use App\Purchasing\Application\Posting\PurchaseInvoiceEntry;
use App\Purchasing\Domain\Error\SupplierInactive;
use App\Purchasing\Domain\Repository\PayableRepository;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * One transaction: the checks (draft, supplier's number, a line, payments = total neto, a date not in the future, an
 * active supplier), then the internal number (row-locked), the payables and the entry. The ledger refuses a date on
 * or before the fecha de bloqueo (period_locked), which rolls everything back, the number included.
 */
final class EmitPurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly PayableRepository $payables,
        private readonly TerceroDirectory $terceros,
        private readonly ProductCatalog $products,
        private readonly Numbering $numbering,
        private readonly JournalPoster $poster,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(EmitPurchaseInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        $today = ColombianCalendar::today($this->clock);
        $invoice->assertEmittable($today);
        if (!$this->terceros->get($command->companyId, $invoice->terceroId())->active) {
            throw new SupplierInactive();
        }

        $products = [];
        foreach ($invoice->lines() as $line) {
            if (null !== $line->productId()) {
                $product = $this->products->get($command->companyId, $line->productId());
                $products[$product->id] = new ProductAccounting('producto' === $product->type, null === $product->expenseAccountId ? null : Uuid::fromString($product->expenseAccountId));
            }
        }

        $number = $this->numbering->purchaseInvoice($command->companyId);
        foreach ($invoice->emit($number->prefix, $number->sequence, $command->userId, $this->clock->now(), $today) as $payable) {
            $this->payables->add($payable);
        }
        $invoice->recordEntry($this->poster->post(PurchaseInvoiceEntry::draft($invoice, $products, $command->userId)));
    }
}
