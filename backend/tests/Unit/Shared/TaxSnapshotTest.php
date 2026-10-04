<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Totals\TaxBase;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TaxSnapshotTest extends TestCase
{
    public function testAReteIvaSnapshotIsComputedOnTheChargeTax(): void
    {
        $reteIva = new TaxSnapshot(Uuid::v7(), 'ReteIVA 15 %', 'reteiva', TaxCalculation::Percentage, '15.0000', null);

        self::assertSame(TaxBase::ChargeTax, $reteIva->rate()->base, 'A document copies a ReteIVA; its rate applies to the IVA whatever item wrote the line.');
    }

    public function testOtherTaxesAreComputedOnTheSubtotal(): void
    {
        $reteFuente = new TaxSnapshot(Uuid::v7(), 'ReteFuente 4 %', 'retefuente', TaxCalculation::Percentage, '4.0000', null);

        self::assertSame(TaxBase::Subtotal, $reteFuente->rate()->base);
    }
}
