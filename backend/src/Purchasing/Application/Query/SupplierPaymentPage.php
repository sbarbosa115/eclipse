<?php

namespace App\Purchasing\Application\Query;

use App\Purchasing\Domain\Model\SupplierPayment;

final readonly class SupplierPaymentPage
{
    /**
     * @param list<SupplierPayment> $items with their allocations
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
