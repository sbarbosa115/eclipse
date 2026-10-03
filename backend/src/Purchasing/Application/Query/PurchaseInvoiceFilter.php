<?php

namespace App\Purchasing\Application\Query;

/** The list's filters (§4.15): text, status, a date range (both ends included) and the page. */
final readonly class PurchaseInvoiceFilter
{
    public function __construct(
        /** Part of the internal number, the supplier's number or the supplier's name, matched literally. */
        public ?string $query = null,
        public ?string $status = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
