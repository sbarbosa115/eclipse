<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Model\SalesInvoice;
use Symfony\Component\Uid\Uuid;

/** What the sales invoice screens read. Scoped to one company: another company's id is "not found". */
interface SalesInvoiceQueries
{
    /** Newest first: by date, then by number (drafts, which have none, first). */
    public function search(Uuid $companyId, SalesInvoiceFilter $filter): SalesInvoicePage;

    /** @throws \App\Sales\Domain\Error\SalesInvoiceNotFound */
    public function get(Uuid $companyId, Uuid $id): SalesInvoice;

    /** @return list<Receivable> */
    public function receivables(Uuid $companyId, Uuid $invoiceId): array;
}
