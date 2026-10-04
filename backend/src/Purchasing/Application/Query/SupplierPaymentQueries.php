<?php

namespace App\Purchasing\Application\Query;

use App\Purchasing\Domain\Model\SupplierPayment;
use Symfony\Component\Uid\Uuid;

/** What the recibo de pago screens read. Scoped to one company: another company's id is "not found". */
interface SupplierPaymentQueries
{
    /** Newest first: by date, then by number. */
    public function search(Uuid $companyId, SupplierPaymentFilter $filter): SupplierPaymentPage;

    /** @throws \App\Purchasing\Domain\Error\SupplierPaymentNotFound */
    public function get(Uuid $companyId, Uuid $id): SupplierPayment;
}
