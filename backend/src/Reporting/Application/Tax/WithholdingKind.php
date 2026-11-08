<?php

namespace App\Reporting\Application\Tax;

/** The three kinds of retención, in the order the reports list them (the tax's `kind` on the wire). */
enum WithholdingKind: string
{
    case Income = 'retefuente';
    case Vat = 'reteiva';
    case Ica = 'reteica';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Retefuente',
            self::Vat => 'ReteIVA',
            self::Ica => 'ReteICA',
        };
    }

    /**
     * The kinds a report covers: one, or all three.
     *
     * @return list<self>
     */
    public static function covering(?self $kind): array
    {
        return null === $kind ? self::cases() : [$kind];
    }
}
