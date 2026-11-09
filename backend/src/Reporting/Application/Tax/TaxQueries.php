<?php

namespace App\Reporting\Application\Tax;

use Symfony\Component\Uid\Uuid;

/**
 * The tax figures of the documents (observations PRD §4.7): the retenciones of the lines (`*_line_withholding`, each with
 * its snapshot, base and share) and the lines' charge taxes, of the invoices that count. An invoice counts when it is
 * emitted, partially paid or paid, by its issue date; drafts never, and voided ones are only counted apart.
 */
interface TaxQueries
{
    /**
     * By month, kind, tercero and tax (name and rate as snapshotted): month first, then retefuente, reteiva, reteica,
     * then the tercero's name.
     *
     * @param list<WithholdingKind> $kinds
     *
     * @return list<WithholdingLine>
     */
    public function withholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, array $kinds): array;

    /**
     * Voided invoices issued in the range that carried a retención of these kinds.
     *
     * @param list<WithholdingKind> $kinds
     */
    public function voidedWithWithholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, array $kinds): int;

    /**
     * IVA by rate (the lines whose charge tax is of kind `iva`): of sales (generado) or of purchases (descontable), the
     * highest rate first.
     *
     * @return list<VatRate>
     */
    public function vatByRate(Uuid $companyId, bool $sales, MonthRange $range): array;

    /** The impoconsumo of the sales lines in the range, as a decimal string. */
    public function consumptionTax(Uuid $companyId, MonthRange $range): string;

    /** One kind of retención on one side in the range, as a decimal string. */
    public function withholdingTotal(Uuid $companyId, WithholdingSide $side, WithholdingKind $kind, MonthRange $range): string;

    /** Voided invoices, sales and purchases, issued in the range. */
    public function voidedInvoices(Uuid $companyId, MonthRange $range): int;
}
