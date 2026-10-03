<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Accounting\PostingConcept;

enum TaxKind: string
{
    case None = 'none';
    case Vat = 'iva';
    case Consumption = 'impoconsumo';
    case IncomeWithholding = 'retefuente';
    case VatWithholding = 'reteiva';
    case IcaWithholding = 'reteica';

    /** The posting concept of this tax on a sale (generated or suffered) and on a purchase (deductible or practiced). */
    public function concept(bool $onSales): ?PostingConcept
    {
        return match ($this) {
            self::None => null,
            self::Vat => $onSales ? PostingConcept::VatGenerated : PostingConcept::VatDeductible,
            self::Consumption => $onSales ? PostingConcept::ConsumptionTax : null,
            self::IncomeWithholding => $onSales ? PostingConcept::WithholdingSuffered : PostingConcept::WithholdingPracticed,
            self::VatWithholding => $onSales ? PostingConcept::VatWithholdingSuffered : PostingConcept::VatWithholdingPracticed,
            self::IcaWithholding => $onSales ? PostingConcept::IcaWithholdingSuffered : PostingConcept::IcaWithholdingPracticed,
        };
    }
}
