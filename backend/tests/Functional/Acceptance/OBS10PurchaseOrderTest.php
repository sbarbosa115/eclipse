<?php

namespace App\Tests\Functional\Acceptance;

use App\Ledger\Domain\Model\JournalEntry;
use App\Tests\Functional\Purchasing\PurchaseOrderFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Observations PRD §8, OBS-10: a purchase order is emitted (OC-1), e-mailed, converted into a draft purchase invoice
 * with identical lines and no number; the libro diario shows no entry for the order; a second conversion is refused.
 */
final class OBS10PurchaseOrderTest extends ApiTestCase
{
    use PurchaseOrderFixtures;

    public function testAnOrderIsEmittedSentAndConvertedOnce(): void
    {
        $this->startPurchasing();
        $product = $this->product();
        $draft = $this->orderDraft(null, ['lines' => [
            $this->orderLine(['product_id' => $product, 'account_id' => null, 'description' => 'Resmas', 'quantity' => '3', 'unit_price' => '20000', 'discount' => '5']),
            $this->orderLine(['description' => 'Servicio de mantenimiento']),
        ]]);

        // Emitted as OC-1 and e-mailed.
        $order = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit-and-send', []);
        self::assertResponseIsSuccessful();
        self::assertSame(['emitted', 'OC-1'], [$order['status'], $order['number']]);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? self::fail('No e-mail.'), 'To', 'ventas@papeleria.co');

        // The libro diario shows no entry for it.
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal')['total'], 'The libro diario is empty.');
        $this->em()->clear();
        self::assertSame([], $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company]), 'No journal entry.');

        // Converted into a draft invoice with identical lines and no number…
        $converted = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);
        self::assertResponseIsSuccessful();
        $invoice = $this->getJson('/api/v1/purchase-invoices/'.$converted['converted_invoice_id']);
        self::assertSame(['draft', null, null, $order['id']], [$invoice['status'], $invoice['number'], $invoice['supplier_invoice_number'], $invoice['purchase_order_id']]);
        $shape = static fn (array $lines) => array_map(static fn (array $l) => [$l['product_id'], $l['account_id'], $l['product_code'], $l['description'], $l['quantity'], $l['unit_price'], $l['discount'], $l['charge_tax_id'], $l['total_amount']], $lines);
        self::assertSame($shape($order['lines']), $shape($invoice['lines']), 'Identical lines.');
        self::assertSame($order['net_total'], $invoice['net_total']);
        self::assertSame('converted', $this->getJson('/api/v1/purchase-orders/'.$order['id'])['status']);
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal')['total'], 'Still nothing in the books.');

        // …and a second conversion is refused.
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('purchase_order_already_converted', $body['error']);
        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total']);
    }
}
