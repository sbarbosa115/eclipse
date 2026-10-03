<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\PurchaseInvoice;
use Symfony\Component\Uid\Uuid;

interface PurchaseInvoiceRepository
{
    /** @throws \App\Purchasing\Domain\Error\PurchaseInvoiceNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): PurchaseInvoice;

    /** Is this supplier's invoice number already recorded on another of the company's invoices (letter case aside)? */
    public function supplierNumberTaken(Uuid $companyId, Uuid $terceroId, string $number, ?Uuid $exceptId = null): bool;

    public function add(PurchaseInvoice $invoice): void;

    public function remove(PurchaseInvoice $invoice): void;
}
