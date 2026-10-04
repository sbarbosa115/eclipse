<?php

namespace App\Catalog\Domain\Model;

/**
 * The unidades de medida a product can use (§9 Q21): a short list of DIAN's, with the code the DIAN tables give them
 * (UN/ECE Recommendation 20). The full list arrives with electronic invoicing in stage 4. The only place the list is
 * written: validation, the picker (GET /products/units) and the default all read it.
 *
 *     94   Unidad       the default for goods sold by the piece
 *     KGM  Kilogramo    goods sold by weight
 *     MTR  Metro        goods sold by length
 *     HUR  Hora         time (consulting, labour)
 *     ZZ   Servicio     "mutuamente definido": what DIAN says to use for a service that has no unit
 */
enum UnitOfMeasure: string
{
    case Unit = '94';
    case Kilogram = 'KGM';
    case Meter = 'MTR';
    case Hour = 'HUR';
    case Service = 'ZZ';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidad',
            self::Kilogram => 'Kilogramo',
            self::Meter => 'Metro',
            self::Hour => 'Hora',
            self::Service => 'Servicio',
        };
    }

    /** What a new product of this type gets when it names no unit. */
    public static function defaultFor(ProductType $type): self
    {
        return ProductType::Service === $type ? self::Service : self::Unit;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $u) => $u->value, self::cases());
    }
}
