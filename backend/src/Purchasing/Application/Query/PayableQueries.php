<?php

namespace App\Purchasing\Application\Query;

use Symfony\Component\Uid\Uuid;

/** What is owed to suppliers: for the supplier payment (item 12) and cartera de proveedores (item 13). */
interface PayableQueries
{
    /** @return list<PayableView> the supplier's payables with a balance left, oldest due first */
    public function openFor(Uuid $companyId, Uuid $terceroId): array;

    /** @return list<PayableView> an invoice's payables, voided ones too */
    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array;
}
