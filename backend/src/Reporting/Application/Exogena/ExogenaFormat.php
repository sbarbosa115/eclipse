<?php

namespace App\Reporting\Application\Exogena;

/**
 * The exógena formats Mustang prepares as working papers (observations PRD §4.7 d), each with its value columns in the
 * DIAN's order. 1001 and 1007 are reported per account and concept; the rest per tercero.
 */
enum ExogenaFormat: string
{
    case Payments = '1001';
    case WithholdingsSuffered = '1003';
    case VatDeductible = '1005';
    case VatGenerated = '1006';
    case Income = '1007';
    case Receivables = '1008';
    case Payables = '1009';

    /** @return array<string, string> the value columns: key on the wire → its heading in the files */
    public function columns(): array
    {
        return match ($this) {
            self::Payments => [
                'payment' => 'Pago o abono en cuenta',
                'vat_cost' => 'IVA mayor valor del costo',
                'withholding' => 'Retención en la fuente practicada',
                'vat_withholding' => 'Retención de IVA practicada',
            ],
            self::WithholdingsSuffered => ['base' => 'Base sometida a retención', 'withholding' => 'Retención que le practicaron'],
            self::VatDeductible => ['deductible_vat' => 'IVA descontable', 'returned_vat' => 'IVA resultante por devoluciones'],
            self::VatGenerated => ['generated_vat' => 'IVA generado', 'consumption_tax' => 'Impuesto al consumo'],
            self::Income => ['gross_income' => 'Ingresos brutos recibidos', 'returns' => 'Devoluciones, rebajas y descuentos'],
            self::Receivables, self::Payables => ['balance' => 'Saldo al 31 de diciembre'],
        };
    }

    /** @return list<string> */
    public function valueColumns(): array
    {
        return array_keys($this->columns());
    }

    /** Reported per account, under the concept of the account (the concept table, Ledger). */
    public function hasConcepts(): bool
    {
        return self::Payments === $this || self::Income === $this;
    }

    public function title(): string
    {
        return match ($this) {
            self::Payments => 'Formato 1001 · Pagos o abonos en cuenta y retenciones practicadas',
            self::WithholdingsSuffered => 'Formato 1003 · Retenciones en la fuente que le practicaron',
            self::VatDeductible => 'Formato 1005 · IVA descontable',
            self::VatGenerated => 'Formato 1006 · IVA generado',
            self::Income => 'Formato 1007 · Ingresos recibidos',
            self::Receivables => 'Formato 1008 · Cuentas por cobrar al 31 de diciembre',
            self::Payables => 'Formato 1009 · Cuentas por pagar al 31 de diciembre',
        };
    }
}
