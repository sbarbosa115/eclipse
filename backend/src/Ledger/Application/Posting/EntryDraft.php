<?php

namespace App\Ledger\Application\Posting;

use Symfony\Component\Uid\Uuid;

/**
 * What a document asks the ledger to post when it is emitted.
 */
final readonly class EntryDraft
{
    /**
     * @param list<EntryLine> $lines
     */
    public function __construct(
        public Uuid $companyId,
        public \DateTimeImmutable $date,
        /** sales_invoice, cash_receipt, purchase_invoice, supplier_payment… */
        public string $sourceType,
        public Uuid $sourceId,
        public string $sourceNumber,
        public string $description,
        public Uuid $userId,
        public array $lines,
    ) {
    }
}
