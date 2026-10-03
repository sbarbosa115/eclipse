<?php

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\Receivable;
use Symfony\Component\Uid\Uuid;

interface ReceivableRepository
{
    /** @throws \App\Sales\Domain\Error\ReceivableNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): Receivable;

    public function add(Receivable $receivable): void;

    /** @return list<Receivable> the invoice's receivables, by due date */
    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array;
}
