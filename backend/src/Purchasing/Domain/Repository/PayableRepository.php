<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\Payable;
use Symfony\Component\Uid\Uuid;

interface PayableRepository
{
    /** @throws \App\Purchasing\Domain\Error\PayableNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): Payable;

    /** @return list<Payable> an invoice's payables, by due date */
    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array;

    public function add(Payable $payable): void;
}
