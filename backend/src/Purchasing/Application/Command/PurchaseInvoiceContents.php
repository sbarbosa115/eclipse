<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** What the person wrote on a draft: shapes already checked, references not yet (PurchaseDraftResolver). */
final readonly class PurchaseInvoiceContents
{
    /**
     * @param list<PurchaseLineContents>    $lines
     * @param list<PurchasePaymentContents> $payments
     */
    public function __construct(
        public Uuid $terceroId,
        public ?string $supplierInvoiceNumber,
        public \DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $dueDate,
        public ?string $notes,
        public array $lines,
        public array $payments,
    ) {
    }
}
