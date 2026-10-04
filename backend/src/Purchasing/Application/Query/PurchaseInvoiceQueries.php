<?php

namespace App\Purchasing\Application\Query;

use Symfony\Component\Uid\Uuid;

interface PurchaseInvoiceQueries
{
    /** @throws \App\Purchasing\Domain\Error\PurchaseInvoiceNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): PurchaseInvoiceView;

    /** Newest first (by date, then number). */
    public function search(Uuid $companyId, PurchaseInvoiceFilter $filter): PurchaseInvoicePage;
}
