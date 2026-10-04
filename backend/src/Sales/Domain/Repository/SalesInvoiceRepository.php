<?php

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\SalesInvoice;
use Symfony\Component\Uid\Uuid;

interface SalesInvoiceRepository
{
    /** @throws \App\Sales\Domain\Error\SalesInvoiceNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): SalesInvoice;

    /**
     * The invoice, locked for update until the transaction ends: a void takes it so a receipt or payment that locks
     * the same invoice cannot be applied at the same moment.
     */
    public function lock(Uuid $companyId, Uuid $id): SalesInvoice;

    public function add(SalesInvoice $invoice): void;

    /** Whether a receipt that is not voided applied money to the invoice (§4.12: it must be voided first). */
    public function hasAllocations(Uuid $companyId, Uuid $invoiceId): bool;
}
