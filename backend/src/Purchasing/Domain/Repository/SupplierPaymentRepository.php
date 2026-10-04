<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\SupplierPayment;
use Symfony\Component\Uid\Uuid;

interface SupplierPaymentRepository
{
    /** @throws \App\Purchasing\Domain\Error\SupplierPaymentNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): SupplierPayment;

    /**
     * The payment, locked for update until the transaction ends and read afresh: two voids of the same payment run one
     * after the other, so its allocations are given back once.
     *
     * @throws \App\Purchasing\Domain\Error\SupplierPaymentNotFound
     */
    public function lock(Uuid $companyId, Uuid $id): SupplierPayment;

    public function add(SupplierPayment $payment): void;
}
