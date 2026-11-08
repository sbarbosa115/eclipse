<?php

namespace App\Purchasing\Application\Query;

final readonly class PurchaseOrderPage
{
    /**
     * @param list<PurchaseOrderSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
