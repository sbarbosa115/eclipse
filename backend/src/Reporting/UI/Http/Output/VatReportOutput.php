<?php

namespace App\Reporting\UI\Http\Output;

/** The IVA working paper for a range of months (observations PRD §4.7 c). */
final readonly class VatReportOutput
{
    /**
     * @param list<VatRateRowOutput> $generated  IVA generado by rate (sales)
     * @param list<VatRateRowOutput> $deductible IVA descontable by rate (purchases)
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $generated,
        public array $deductible,
        public string $generatedTotal,
        public string $deductibleTotal,
        public string $reteivaPracticed,
        public string $reteivaSuffered,
        public string $impoconsumo,
        /** generado − descontable: positive a pagar, negative a favor. */
        public string $net,
        /** Según libros: 2408 generado and descontable in the period. */
        public string $ledgerGenerated,
        public string $ledgerDeductible,
        public int $voidedCount,
    ) {
    }
}
