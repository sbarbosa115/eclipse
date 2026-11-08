<?php

namespace App\Purchasing\Application\Query;

/** The list's filters: text, status, a date range (both ends included), the supplier, a page. */
final readonly class PurchaseOrderFilter
{
    public function __construct(
        /** Part of the number or of the supplier's name, matched literally. */
        public ?string $query = null,
        public ?string $status = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $terceroId = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
