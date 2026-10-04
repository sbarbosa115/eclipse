<?php

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\Quotation;
use Symfony\Component\Uid\Uuid;

interface QuotationRepository
{
    /** @throws \App\Sales\Domain\Error\QuotationNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): Quotation;

    public function add(Quotation $quotation): void;
}
