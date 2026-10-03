<?php

namespace App\Company\Application\Numbering;

use App\Company\Domain\Model\SeriesKind;
use App\Company\Domain\Repository\NumberingSeriesRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Hands out document numbers inside the emitting transaction: each call locks its series row until the transaction
 * ends, so concurrent emissions wait for each other and a number is never given twice. A rolled-back emission gives
 * its number back with the rollback.
 *
 * The sales invoice's authorised number comes from the invoicing resolution (SalesInvoiceNumbering, item "company").
 */
final class Numbering
{
    public function __construct(private readonly NumberingSeriesRepository $series)
    {
    }

    public function quotation(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::Quotation);
    }

    public function cashReceipt(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::CashReceipt);
    }

    public function purchaseInvoice(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::PurchaseInvoice);
    }

    public function supplierPayment(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::SupplierPayment);
    }

    /** The internal consecutive every sales invoice also carries (§4.8). */
    public function salesInvoiceInternal(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::SalesInvoiceInternal);
    }

    public function journalEntry(Uuid $companyId): DocumentNumber
    {
        return $this->take($companyId, SeriesKind::JournalEntry);
    }

    private function take(Uuid $companyId, SeriesKind $kind): DocumentNumber
    {
        $series = $this->series->lock($companyId, $kind);

        return new DocumentNumber($series->prefix(), $series->take());
    }
}
