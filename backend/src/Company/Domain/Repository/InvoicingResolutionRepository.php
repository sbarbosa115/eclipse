<?php

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\InvoicingResolution;
use Symfony\Component\Uid\Uuid;

interface InvoicingResolutionRepository
{
    public function current(Uuid $companyId): ?InvoicingResolution;

    /** The company's resolution, locked for update until the transaction ends. */
    public function lockCurrent(Uuid $companyId): ?InvoicingResolution;

    public function add(InvoicingResolution $resolution): void;
}
