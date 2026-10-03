<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchasePaymentDraft;
use Symfony\Component\Uid\Uuid;

/** A draft's contents checked against the catalogs, ready for PurchaseInvoice::revise(). */
final readonly class ResolvedDraft
{
    /**
     * @param list<PurchaseLineDraft>    $lines
     * @param list<PurchasePaymentDraft> $payments
     */
    public function __construct(
        public Uuid $terceroId,
        public string $terceroName,
        public ?string $supplierInvoiceNumber,
        public \DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $dueDate,
        public ?string $notes,
        public array $lines,
        public array $payments,
    ) {
    }

    public function applyTo(PurchaseInvoice $invoice): void
    {
        $invoice->revise($this->terceroId, $this->terceroName, $this->supplierInvoiceNumber, $this->issueDate, $this->dueDate, $this->notes, $this->lines, $this->payments);
    }
}
