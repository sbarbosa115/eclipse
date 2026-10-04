<?php

namespace App\Sales\Application\Query;

use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\Receivable;
use Symfony\Component\Uid\Uuid;

/** What the recibo de caja screens read. Scoped to one company: another company's id is "not found". */
interface CashReceiptQueries
{
    /** Newest first: by date, then by number. */
    public function search(Uuid $companyId, CashReceiptFilter $filter): CashReceiptPage;

    /** @throws \App\Sales\Domain\Error\CashReceiptNotFound */
    public function get(Uuid $companyId, Uuid $id): CashReceipt;

    /**
     * What the client still owes (§4.9): its receivables with a balance, of invoices not voided, the oldest due first.
     *
     * @return list<Receivable>
     */
    public function openReceivables(Uuid $companyId, Uuid $terceroId): array;
}
