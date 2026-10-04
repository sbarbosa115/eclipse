<?php

namespace App\Reporting\Application\Cartera;

/** One open receivable or payable, for the drill-down: the document it came from and how late it is. */
final readonly class CarteraDocument
{
    public function __construct(
        /** The receivable or payable. */
        public string $id,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $terceroId,
        public string $terceroName,
        public string $issueDate,
        public string $dueDate,
        public string $amount,
        public string $balance,
        /** Negative while not yet due. */
        public int $daysOverdue,
        public AgeingBucket $bucket,
    ) {
    }
}
