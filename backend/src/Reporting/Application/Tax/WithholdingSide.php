<?php

namespace App\Reporting\Application\Tax;

/**
 * Whose retenciones (observations PRD §4.7 a, b): practicadas, what the company withheld from its suppliers (facturas de
 * compra, owed to the DIAN in 2365/2367/2368); sufridas, what its clients withheld from it (facturas de venta, an asset
 * in 135515/135517/135518).
 */
enum WithholdingSide: string
{
    case Practiced = 'practiced';
    case Suffered = 'suffered';

    /** The account family a kind of retención is posted to on this side (its concept's account, §5). */
    public function ledgerPrefix(WithholdingKind $kind): string
    {
        return match ($this) {
            self::Practiced => match ($kind) {
                WithholdingKind::Income => '2365',
                WithholdingKind::Vat => '2367',
                WithholdingKind::Ica => '2368',
            },
            self::Suffered => match ($kind) {
                WithholdingKind::Income => '135515',
                WithholdingKind::Vat => '135517',
                WithholdingKind::Ica => '135518',
            },
        };
    }

    /** A practicada is a liability (credit increases it), a sufrida an asset (debit increases it). */
    public function growsOnCredit(): bool
    {
        return self::Practiced === $this;
    }

    public function title(): string
    {
        return self::Practiced === $this ? 'Retenciones practicadas' : 'Retenciones sufridas';
    }

    public function slug(): string
    {
        return self::Practiced === $this ? 'retenciones-practicadas' : 'retenciones-sufridas';
    }
}
