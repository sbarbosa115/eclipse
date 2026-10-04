<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\Quotation;
use Symfony\Component\Uid\Uuid;

/** What the quotation screens read. Scoped to one company: another company's id is "not found". */
interface QuotationQueries
{
    /**
     * Newest first. `$today` is Colombia's day: it decides which emitted quotations read as expired, for the status
     * filter too.
     */
    public function search(Uuid $companyId, QuotationFilter $filter, \DateTimeImmutable $today): QuotationPage;

    /** @throws \App\Sales\Domain\Error\QuotationNotFound */
    public function get(Uuid $companyId, Uuid $id): Quotation;
}
