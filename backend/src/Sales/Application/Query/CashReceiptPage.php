<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\CashReceipt;

final readonly class CashReceiptPage
{
    /**
     * @param list<CashReceipt> $items with their allocations
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
