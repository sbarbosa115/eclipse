<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\SalesInvoice;

final readonly class SalesInvoicePage
{
    /**
     * @param list<SalesInvoice> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
