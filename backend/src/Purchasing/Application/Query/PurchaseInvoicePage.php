<?php

namespace App\Purchasing\Application\Query;

final readonly class PurchaseInvoicePage
{
    /**
     * @param list<PurchaseInvoiceSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
