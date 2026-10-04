<?php

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\CashReceipt;
use Symfony\Component\Uid\Uuid;

interface CashReceiptRepository
{
    /** @throws \App\Sales\Domain\Error\CashReceiptNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): CashReceipt;

    /**
     * The receipt, locked for update until the transaction ends and read afresh: two voids of the same receipt run one
     * after the other, so its allocations are given back once.
     *
     * @throws \App\Sales\Domain\Error\CashReceiptNotFound
     */
    public function lock(Uuid $companyId, Uuid $id): CashReceipt;

    public function add(CashReceipt $receipt): void;
}
