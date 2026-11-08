<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Functional\Purchasing\PurchasingFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Observations PRD §8, OBS-3, the posting half: "The same with a purchase invoice: Cr 2365xx, 2367xx, 2368xx per
 * retención". (The retenciones practicadas report and its *Según libros* are item 8's half.).
 */
final class Obs03PurchaseInvoicePostsEachRetencionTest extends ApiTestCase
{
    use PurchasingFixtures;
    use WithholdingTaxes;

    public function testEachRetencionIsCreditedToItsOwnAccountWithTheSupplier(): void
    {
        $this->startPurchasing();
        $lines = $this->payload()['lines'];
        $lines[0]['withholdings'] = self::withholdings($this->taxId('ReteFuente compras 2,5 %', 'withholding'), $this->reteIca(), $this->taxId('ReteIVA 15 %', 'withholding'));

        $invoice = $this->emitInvoice(['lines' => $lines, 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1126840.00', 'due_date' => self::today(29)]]]);

        self::assertSame(['63160.00', '1126840.00'], [$invoice['withholding_total'], $invoice['net_total']]);
        [$entry] = $this->entries();
        $supplier = $this->supplier->toRfc4122();
        $movements = self::movements($entry);
        self::assertSame([
            ['513595', '1000000.00', '0.00', null],
            ['240810', '190000.00', '0.00', null],
        ], \array_slice($movements, 0, 2), 'Dr the expense and the IVA descontable.');
        $credits = \array_slice($movements, 2, 3);
        self::assertSame([
            ['2365', '0.00', '25000.00', $supplier],
            ['2368', '0.00', '9660.00', $supplier],
            ['2367', '0.00', '28500.00', $supplier],
        ], array_map(static fn (array $m) => [substr($m[0], 0, 4), $m[1], $m[2], $m[3]], $credits), 'One credit per retención account, with the supplier: 2365xx, 2368xx, 2367xx.');
        self::assertSame(['236540', '236701'], [$credits[0][0], $credits[2][0]], 'Each tax\'s own purchase account.');
        self::assertSame(['22050501', '0.00', '1126840.00', $supplier], $movements[5], 'Cr proveedores for the net.');
        self::assertTrue($entry->isBalanced(), 'Balanced to the cent.');
    }
}
