<?php

namespace App\Ledger\Application\Query;

final readonly class JournalEntryView
{
    /**
     * @param list<JournalLineView> $lines
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $date,
        public string $sourceType,
        public string $sourceId,
        public string $sourceNumber,
        public string $description,
        public ?string $reversesId,
        public string $totalDebit,
        public string $totalCredit,
        public array $lines,
    ) {
    }
}
