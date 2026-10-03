<?php

namespace App\Tests\Functional\Acceptance;

use App\Purchasing\Infrastructure\Query\DoctrinePayableQueries;
use App\Tests\Functional\Purchasing\PurchasingFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §7, acceptance criterion 5: "A purchase invoice for a service with IVA 19 % and ReteFuente 4 % on credit posts
 * Dr gasto, Dr IVA descontable, Cr 2365, Cr 2205, balanced; cartera de proveedores shows the payable at its net amount."
 */
final class Ac5PurchaseInvoiceTest extends ApiTestCase
{
    use PurchasingFixtures;

    public function testAServicePurchaseWithIvaAndReteFuenteOnCredit(): void
    {
        $this->startPurchasing();
        $service = $this->sendJson('POST', '/api/v1/products', [
            'type' => 'servicio', 'code' => 'MNT-01', 'name' => 'Mantenimiento', 'sale_price' => '0', 'price_includes_tax' => false,
            'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %', 'withholding'),
        ]);
        self::assertResponseStatusCodeSame(201);

        $invoice = $this->emitInvoice(['lines' => [[
            'product_id' => $service['id'], 'account_id' => null, 'description' => 'Mantenimiento de equipos', 'quantity' => '1', 'unit_price' => '1000000', 'discount' => '0',
            'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %', 'withholding'),
        ]]]);

        [$entry] = $this->entries();
        $supplier = $this->supplier->toRfc4122();
        self::assertSame([
            ['519595', '1000000.00', '0.00', null],
            ['240810', '190000.00', '0.00', null],
            ['236525', '0.00', '40000.00', $supplier],
            ['22050501', '0.00', '1150000.00', $supplier],
        ], self::movements($entry), 'Dr gasto (gasto por defecto for a service with no account of its own), Dr IVA descontable, Cr 2365, Cr 2205.');
        self::assertTrue($entry->isBalanced(), 'Balanced to the cent.');

        $open = (new DoctrinePayableQueries($this->em()))->openFor($this->company, $this->supplier);
        self::assertCount(1, $open, 'Cartera de proveedores shows one payable.');
        self::assertSame('1150000.00', $open[0]->balance, 'At its net amount: 1 000 000 + 190 000 − 40 000.');
        self::assertSame($invoice['number'], $open[0]->invoiceNumber);
    }
}
