<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Application\Query\PurchaseInvoicePdf;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Observations PRD §4.4 on a factura de compra: a line by product snapshots the product's code, a line by account
 * has none, a copy passes the snapshot on and the PDF prints the Código column.
 */
final class PurchaseProductCodeTest extends ApiTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    public function testALineByProductSnapshotsItsCodeAndALineByAccountHasNone(): void
    {
        $product = $this->sendJson('POST', '/api/v1/products', ['type' => 'producto', 'code' => 'RES-01', 'name' => 'Resma carta', 'sale_price' => '20000', 'price_includes_tax' => false]);
        self::assertResponseStatusCodeSame(201);
        $byProduct = ['product_id' => $product['id'], 'account_id' => null, 'description' => 'Resma', 'quantity' => '1', 'unit_price' => '10000', 'discount' => '0', 'charge_tax_id' => null, 'withholding_tax_id' => null];
        $byAccount = ['product_id' => null, 'account_id' => $this->account('513595')->toRfc4122()] + $byProduct;

        $draft = $this->createDraft(['lines' => [$byProduct, $byAccount], 'payments' => [['payment_method_id' => $this->methodId('Efectivo'), 'amount' => '20000.00', 'due_date' => null]]]);
        self::assertSame(['RES-01', null], array_column($draft['lines'], 'product_code'));

        $this->sendJson('PUT', '/api/v1/products/'.$product['id'], ['type' => 'producto', 'code' => 'RES-99', 'name' => 'Resma renombrada', 'sale_price' => '20000', 'price_includes_tax' => false]);
        self::assertResponseIsSuccessful();
        self::assertSame('RES-01', $this->getJson('/api/v1/purchase-invoices/'.$draft['id'])['lines'][0]['product_code'], 'A recoded product leaves the document alone.');

        $copy = $this->sendJson('POST', '/api/v1/purchase-invoices/'.$draft['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['RES-01', null], array_column($copy['lines'], 'product_code'), 'A copy passes the snapshot on.');

        $html = static::getContainer()->get(Environment::class)->render(PurchaseInvoicePdf::TEMPLATE, static::getContainer()->get(PurchaseInvoicePdf::class)->context($this->company, Uuid::fromString($draft['id'])));
        self::assertMatchesRegularExpression('#<th>Código</th>\s*<th>Descripción</th>#', $html);
        self::assertStringContainsString('<td>RES-01</td>', $html);
    }
}
