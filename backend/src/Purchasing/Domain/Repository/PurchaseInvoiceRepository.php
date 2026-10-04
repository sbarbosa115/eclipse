<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\PurchaseInvoice;
use Symfony\Component\Uid\Uuid;

interface PurchaseInvoiceRepository
{
    /** @throws \App\Purchasing\Domain\Error\PurchaseInvoiceNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): PurchaseInvoice;

    /**
     * The invoice, locked for update until the transaction ends: a void takes it so a receipt or payment that locks
     * the same invoice cannot be applied at the same moment.
     */
    public function lock(Uuid $companyId, Uuid $id): PurchaseInvoice;

    /** Is this supplier's invoice number already recorded on another of the company's invoices (letter case aside)? */
    public function supplierNumberTaken(Uuid $companyId, Uuid $terceroId, string $number, ?Uuid $exceptId = null): bool;

    public function add(PurchaseInvoice $invoice): void;

    public function remove(PurchaseInvoice $invoice): void;
}
