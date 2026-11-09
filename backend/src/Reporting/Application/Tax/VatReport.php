<?php

namespace App\Reporting\Application\Tax;

use App\Shared\Domain\Money\Money;

/** The IVA working paper for a range of months (observations PRD §4.7 c). */
final readonly class VatReport
{
    /**
     * @param list<VatRate> $generated  IVA generado by rate (sales)
     * @param list<VatRate> $deductible IVA descontable by rate (purchases)
     */
    public function __construct(
        public MonthRange $range,
        public array $generated,
        public array $deductible,
        public Money $generatedTotal,
        public Money $deductibleTotal,
        public Money $reteivaPracticed,
        public Money $reteivaSuffered,
        public Money $consumptionTax,
        public Money $ledgerGenerated,
        public Money $ledgerDeductible,
        public int $voidedCount,
    ) {
    }

    /**
     * The saldo of the IVA return (formulario 300): generado − descontable − the ReteIVA clients withheld (sufrida, an
     * advance on it). Positive is a pagar, negative a favor. ReteIVA practicada is declared on the retenciones return
     * (350) and impoconsumo on its own (310), so neither moves it.
     */
    public function net(): Money
    {
        return $this->generatedTotal->minus($this->deductibleTotal)->minus($this->reteivaSuffered);
    }

    public function generatedDifference(): Money
    {
        return $this->ledgerGenerated->minus($this->generatedTotal);
    }

    public function deductibleDifference(): Money
    {
        return $this->ledgerDeductible->minus($this->deductibleTotal);
    }
}
