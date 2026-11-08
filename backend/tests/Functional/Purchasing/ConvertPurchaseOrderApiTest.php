<?php

namespace App\Tests\Functional\Purchasing;

use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Support\ApiTestCase;

/**
 * "Convertir en factura" (observations PRD §4.8): a draft purchase invoice with the same supplier and lines (every
 * retención, the product code), dated today, no supplier number and no formas de pago, that remembers the order; the
 * order becomes converted and keeps the invoice; once. What changed since is refused with the invoice's own violations
 * and nothing is converted.
 */
final class ConvertPurchaseOrderApiTest extends ApiTestCase
{
    use PurchaseOrderFixtures;
    use WithholdingTaxes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    /**
     * @return array<string, mixed> the emitted order of two lines (a product with two retenciones, an account)
     */
    private function richOrder(?string $supplier = null): array
    {
        return $this->emittedOrder($supplier, [
            'issue_date' => self::today(-2),
            'notes' => 'Entrega en 5 días',
            'lines' => [
                $this->orderLine(['product_id' => $this->product(), 'account_id' => null, 'description' => 'Resmas', 'quantity' => '10', 'unit_price' => '20000', 'discount' => '5', 'withholdings' => self::withholdings($this->taxId('ReteFuente compras 2,5 %', 'withholding'), $this->reteIca())]),
                $this->orderLine(['charge_tax_id' => null]),
            ],
        ]);
    }

    public function testConvertingMakesADraftInvoiceWithTheSameLinesAndTotals(): void
    {
        $order = $this->richOrder();

        $converted = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        self::assertResponseIsSuccessful();
        self::assertSame('converted', $converted['status']);
        self::assertNotNull($converted['converted_invoice_id'], 'The order keeps the invoice.');
        self::assertSame($converted, $this->getJson('/api/v1/purchase-orders/'.$order['id']));

        $invoice = $this->getJson('/api/v1/purchase-invoices/'.$converted['converted_invoice_id']);
        self::assertSame(['draft', null, $order['id'], self::today()], [$invoice['status'], $invoice['supplier_invoice_number'], $invoice['purchase_order_id'], $invoice['issue_date']], 'No supplier number; it remembers the order; dated today.');
        self::assertSame($order['tercero_id'], $invoice['tercero_id']);
        self::assertSame($order['notes'], $invoice['notes']);
        self::assertSame([], $invoice['payments'], 'Formas de pago are chosen on the invoice.');
        self::assertSame(
            [$order['gross_total'], $order['discount_total'], $order['subtotal'], $order['tax_total'], $order['withholding_total'], $order['net_total']],
            [$invoice['gross_total'], $invoice['discount_total'], $invoice['subtotal'], $invoice['tax_total'], $invoice['withholding_total'], $invoice['net_total']],
            'Identical totals.',
        );
        $keys = ['product_id', 'account_id', 'product_code', 'description', 'quantity', 'unit_price', 'discount', 'charge_tax_id', 'charge_tax_name', 'subtotal_amount', 'tax_amount', 'withholding_amount', 'total_amount'];
        $pick = static fn (array $lines) => array_map(static fn (array $l) => array_intersect_key($l, array_flip($keys)), $lines);
        self::assertSame($pick($order['lines']), $pick($invoice['lines']), 'Identical lines, taxes and product codes.');
        $withholdings = static fn (array $doc) => array_map(static fn (array $w) => [$w['tax_id'], $w['name'], $w['base_amount'], $w['amount']], $doc['lines'][0]['withholdings']);
        self::assertCount(2, $withholdings($invoice), 'Every retención is copied, not only the first.');
        self::assertSame($withholdings($order), $withholdings($invoice));
        self::assertSame($order['withholdings_by_tax'], $invoice['withholdings_by_tax']);
        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total']);
        self::assertSame(0, $this->journalRows(), 'No entry for the order, and a draft invoice posts nothing.');
    }

    public function testTheDraftInvoiceIsCompletedAndEmittedLikeAnyOther(): void
    {
        $order = $this->richOrder();
        $converted = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);
        $invoice = $this->getJson('/api/v1/purchase-invoices/'.$converted['converted_invoice_id']);

        $this->sendJson('PUT', '/api/v1/purchase-invoices/'.$invoice['id'], [
            'tercero_id' => $invoice['tercero_id'], 'supplier_invoice_number' => 'FP-100', 'issue_date' => $invoice['issue_date'], 'due_date' => null, 'notes' => $invoice['notes'],
            'lines' => array_map(static fn (array $l) => array_intersect_key($l, array_flip(['product_id', 'account_id', 'description', 'quantity', 'unit_price', 'discount', 'charge_tax_id'])) + ['withholdings' => array_map(static fn (array $w) => ['tax_id' => $w['tax_id']], $l['withholdings'])], $invoice['lines']),
            'payments' => [['payment_method_id' => $this->methodId('Efectivo'), 'amount' => $invoice['net_total'], 'due_date' => null]],
        ]);
        self::assertResponseIsSuccessful();
        $emitted = $this->sendJson('POST', '/api/v1/purchase-invoices/'.$invoice['id'].'/emit', []);

        self::assertResponseIsSuccessful(json_encode($emitted) ?: '');
        self::assertSame([$order['id'], 'emitted'], [$emitted['purchase_order_id'], $emitted['status']]);
        self::assertSame('converted', $this->getJson('/api/v1/purchase-orders/'.$order['id'])['status']);
    }

    public function testAnOrderConvertsOnce(): void
    {
        $order = $this->richOrder();
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('purchase_order_already_converted', $body['error']);
        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total'], 'One invoice only.');
    }

    public function testOnlyAnEmittedOrderConverts(): void
    {
        $draft = $this->orderDraft();
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409, 'A draft is not out to the supplier yet.');
        self::assertSame('purchase_order_not_open', $body['error']);

        $voided = $this->emittedOrder();
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$voided['id'].'/void', ['reason' => 'x']);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$voided['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, $this->getJson('/api/v1/purchase-invoices')['total']);
    }

    public function testAConvertedOrderIsFrozenAndNotVoided(): void
    {
        $order = $this->richOrder();
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => 'x']);
        self::assertResponseStatusCodeSame(409);
        $this->sendJson('PUT', '/api/v1/purchase-orders/'.$order['id'], $this->orderPayload($order['tercero_id']));
        self::assertResponseStatusCodeSame(409);
        $copy = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);
        self::assertNull($copy['converted_invoice_id'], 'A copy starts clean and converts on its own.');
    }

    public function testAProductDeactivatedSinceIsRefusedWithTheInvoicesViolationAndNothingChanges(): void
    {
        $product = $this->product('VIEJO');
        $order = $this->emittedOrder(null, ['lines' => [$this->orderLine(['product_id' => $product, 'account_id' => null])]]);
        $this->sendJson('POST', "/api/v1/products/$product/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        self::assertSame('lines[0].product_id', $body['violations'][0]['field']);
        self::assertSame('Este producto está inactivo.', $body['violations'][0]['message']);
        $after = $this->getJson('/api/v1/purchase-orders/'.$order['id']);
        self::assertSame(['emitted', null], [$after['status'], $after['converted_invoice_id']], 'The order is untouched.');
        self::assertSame(0, $this->getJson('/api/v1/purchase-invoices')['total'], 'No invoice was made.');
    }

    public function testAnInactiveSupplierIsRefusedWithTheInvoicesViolation(): void
    {
        $supplier = $this->supplierWithEmail();
        $order = $this->richOrder($supplier);
        $this->sendJson('POST', "/api/v1/terceros/$supplier/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        self::assertSame('tercero_id', $body['violations'][0]['field']);
        self::assertSame(['emitted', null], [$this->getJson('/api/v1/purchase-orders/'.$order['id'])['status'], $this->body()['converted_invoice_id']]);
        self::assertSame(0, $this->getJson('/api/v1/purchase-invoices')['total']);

        // Reactivated, it converts: the refusal left no trace.
        $this->sendJson('POST', "/api/v1/terceros/$supplier/reactivate", []);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);
        self::assertResponseIsSuccessful();
    }

    public function testATaxDeactivatedSinceIsRefusedByFieldPath(): void
    {
        $five = $this->taxId('IVA 5 %');
        $order = $this->emittedOrder(null, ['lines' => [$this->orderLine(['charge_tax_id' => $five])]]);
        $this->sendJson('POST', "/api/v1/taxes/$five/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('lines[0].charge_tax_id', $body['violations'][0]['field']);
        self::assertSame('emitted', $this->getJson('/api/v1/purchase-orders/'.$order['id'])['status']);
    }
}
