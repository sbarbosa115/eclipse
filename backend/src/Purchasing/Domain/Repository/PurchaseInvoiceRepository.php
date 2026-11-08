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

    /** Is this supplier's invoice number already on another of the company's invoices that is not voided (letter case aside)? */
    public function supplierNumberTaken(Uuid $companyId, Uuid $terceroId, string $number, ?Uuid $exceptId = null): bool;

    /**
     * Writes now that a voided invoice no longer holds its supplier's number (the live-number key), so a copy that
     * carries the number can be added in the same transaction: the unit of work inserts before it updates (Corregir,
     * observations PRD §4.6).
     */
    public function releaseSupplierNumber(PurchaseInvoice $voided): void;

    public function add(PurchaseInvoice $invoice): void;

    public function remove(PurchaseInvoice $invoice): void;
}
