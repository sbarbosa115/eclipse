<?php

namespace App\Tests\Functional\Acceptance;

use App\Ledger\Domain\Model\JournalEntry;
use App\Tests\Functional\Sales\QuotationFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * AC-2: a quotation for a client is emitted and sent; the libro diario shows no entry for it. Converting it yields a
 * draft invoice with identical lines and totals, and the quotation shows `aceptada` (accepted).
 */
final class AC02QuotationEmittedAndConvertedTest extends ApiTestCase
{
    use QuotationFixtures;

    public function testAQuotationIsEmittedSentAndConverted(): void
    {
        $this->startCompany();
        $client = $this->client('Cliente Cotizado S.A.S.');
        $consulting = $this->service('CONSULTORIA', '1000000');
        $support = $this->service('SOPORTE', '250000');
        $draft = $this->quotationDraft($this->quotationPayload($client, $consulting, [
            'lines' => [
                $this->line($consulting, ['quantity' => '3', 'discount' => '5']),
                $this->line($support, ['description' => 'Soporte mensual', 'unit_price' => '250000']),
            ],
        ]));

        // Emitted and sent.
        $quotation = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit-and-send', []);
        self::assertResponseIsSuccessful();
        self::assertSame(['emitted', 'C-1'], [$quotation['status'], $quotation['number']]);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? self::fail('No e-mail.'), 'To', 'facturas@cliente.co');

        // The libro diario shows no entry for it.
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal')['total'], 'The libro diario is empty.');
        $this->em()->clear();
        self::assertSame([], $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company]), 'No journal entry.');

        // Converting it yields a draft invoice with identical lines and totals…
        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);
        self::assertResponseIsSuccessful();
        $invoice = $this->getJson('/api/v1/sales-invoices/'.$converted['converted_invoice_id']);
        self::assertSame('draft', $invoice['status']);
        self::assertSame($quotation['id'], $invoice['quotation_id']);
        $shape = static fn (array $lines) => array_map(static fn (array $l) => [$l['product_id'], $l['description'], $l['quantity'], $l['unit_price'], $l['discount'], $l['charge_tax_id'], $l['total_amount']], $lines);
        self::assertSame($shape($quotation['lines']), $shape($invoice['lines']), 'Identical lines.');
        self::assertSame(
            [$quotation['gross_total'], $quotation['discount_total'], $quotation['subtotal'], $quotation['tax_total'], $quotation['net_total']],
            [$invoice['gross_total'], $invoice['discount_total'], $invoice['subtotal'], $invoice['tax_total'], $invoice['net_total']],
            'Identical totals.',
        );

        // …and the quotation shows aceptada.
        self::assertSame('accepted', $this->getJson('/api/v1/quotations/'.$quotation['id'])['status']);
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal')['total'], 'Still nothing in the books: a draft invoice posts nothing.');
    }
}
