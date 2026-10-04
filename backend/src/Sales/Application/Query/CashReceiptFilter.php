<?php

namespace App\Sales\Application\Query;

/** The list's filters (§4.15): text, status, a date range, a client, a page. */
final readonly class CashReceiptFilter
{
    public function __construct(
        /** Part of the number or of the client's name, matched literally. */
        public ?string $query = null,
        /** emitted or voided. */
        public ?string $status = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $terceroId = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
