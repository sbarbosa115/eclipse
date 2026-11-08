<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\VatReport;

/** The IVA working paper for a range of months (observations PRD §4.7 c). */
final readonly class VatReportOutput
{
    /**
     * @param list<VatRateRowOutput> $generated  IVA generado by rate (sales)
     * @param list<VatRateRowOutput> $deductible IVA descontable by rate (purchases)
     */
    public function __construct(
        /** YYYY-MM */
        public string $from,
        public string $to,
        public array $generated,
        public array $deductible,
        public string $generatedTotal,
        public string $deductibleTotal,
        /** What the company withheld of its suppliers' IVA (declared on the retenciones return). */
        public string $reteivaPracticed,
        /** What clients withheld of the company's IVA: discounted from the saldo. */
        public string $reteivaSuffered,
        /** Impoconsumo of the sales (its own return). */
        public string $impoconsumo,
        /** generado − descontable − reteiva sufrida: positive a pagar, negative a favor. */
        public string $net,
        /** Según libros: what 240805 (generado) and 240810 (descontable) moved in the period. */
        public string $ledgerGenerated,
        public string $ledgerDeductible,
        /** ledger − report, each: zero when the books and the documents agree. */
        public string $generatedDifference,
        public string $deductibleDifference,
        /** Voided invoices (sales and purchases) issued in the period, left out. */
        public int $voidedCount,
    ) {
    }

    public static function of(VatReport $r): self
    {
        return new self(
            $r->range->from(),
            $r->range->to(),
            array_map(VatRateRowOutput::of(...), $r->generated),
            array_map(VatRateRowOutput::of(...), $r->deductible),
            $r->generatedTotal->toString(),
            $r->deductibleTotal->toString(),
            $r->reteivaPracticed->toString(),
            $r->reteivaSuffered->toString(),
            $r->consumptionTax->toString(),
            $r->net()->toString(),
            $r->ledgerGenerated->toString(),
            $r->ledgerDeductible->toString(),
            $r->generatedDifference()->toString(),
            $r->deductibleDifference()->toString(),
            $r->voidedCount,
        );
    }
}
