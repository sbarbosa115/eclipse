<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\Quotation;

final readonly class QuotationPage
{
    /**
     * @param list<Quotation> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
