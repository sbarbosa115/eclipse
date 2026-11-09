<?php

namespace App\Purchasing\Application\Query;

use Symfony\Component\Uid\Uuid;

/** What the purchase order screens read. Scoped to one company: another company's id is "not found". */
interface PurchaseOrderQueries
{
    /** Newest first (by date, then number). */
    public function search(Uuid $companyId, PurchaseOrderFilter $filter): PurchaseOrderPage;

    /** @throws \App\Purchasing\Domain\Error\PurchaseOrderNotFound */
    public function get(Uuid $companyId, Uuid $id): PurchaseOrderView;
}
