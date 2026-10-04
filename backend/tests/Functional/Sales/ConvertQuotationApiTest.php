<?php

namespace App\Tests\Functional\Sales;

use App\Ledger\Domain\Model\JournalEntry;
use App\Tests\Support\ApiTestCase;

/**
 * "Convertir a factura" (§4.7, §9 Q17): a draft sales invoice with the same client, contact, lines and taxes that
 * remembers the quotation; the quotation becomes accepted and keeps the invoice; once. A product, tax or client
 * deactivated since is refused by the invoice command and shown by field path, with nothing converted.
 */
final class ConvertQuotationApiTest extends ApiTestCase
{
    use QuotationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /**
     * @return array<string, mixed> the emitted quotation of two lines with discount and retención
     */
    private function richQuotation(?string $client = null): array
    {
        $client ??= $this->client();
        $first = $this->service('UNO');
        $second = $this->service('DOS', '500000', ['charge_tax_id' => null]);
        $draft = $this->quotationDraft($this->quotationPayload($client, $first, [
            'responsible_id' => $this->employee(),
            'notes' => 'Entrega en 5 días',
            'lines' => [
                $this->line($first, ['quantity' => '2', 'discount' => '10', 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %')]),
                $this->line($second, ['description' => 'Soporte', 'unit_price' => '500000', 'charge_tax_id' => null]),
            ],
        ]));
        $emitted = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful();

        return $emitted;
    }

    public function testConvertingMakesADraftInvoiceWithTheSameLinesAndTotals(): void
    {
        $quotation = $this->richQuotation();

        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        self::assertResponseIsSuccessful();
        self::assertSame('accepted', $converted['status'], 'The quotation becomes accepted…');
        self::assertNotNull($converted['converted_invoice_id'], '…and keeps the invoice.');

        $invoice = $this->getJson('/api/v1/sales-invoices/'.$converted['converted_invoice_id']);
        self::assertSame('draft', $invoice['status']);
        self::assertSame($quotation['id'], $invoice['quotation_id'], 'The invoice records its origin (§4.7).');
        self::assertSame([$quotation['tercero_id'], $quotation['contact_id'], $quotation['responsible_id']], [$invoice['tercero_id'], $invoice['contact_id'], $invoice['seller_id']], 'Same client and contact; the responsable is the vendedor.');
        self::assertSame($quotation['notes'], $invoice['notes']);
        self::assertSame(self::today(), $invoice['issue_date']);
        self::assertSame([], $invoice['payments'], 'Formas de pago are chosen on the invoice.');
        self::assertSame(
            [$quotation['gross_total'], $quotation['discount_total'], $quotation['subtotal'], $quotation['tax_total'], $quotation['withholding_total'], $quotation['net_total']],
            [$invoice['gross_total'], $invoice['discount_total'], $invoice['subtotal'], $invoice['tax_total'], $invoice['withholding_total'], $invoice['net_total']],
            'Identical totals.',
        );
        $keys = ['product_id', 'description', 'quantity', 'unit_price', 'discount', 'charge_tax_id', 'charge_tax_name', 'charge_tax_rate', 'withholding_tax_id', 'withholding_tax_name', 'subtotal_amount', 'tax_amount', 'withholding_amount', 'total_amount'];
        $pick = static fn (array $lines) => array_map(static fn (array $l) => array_intersect_key($l, array_flip($keys)), $lines);
        self::assertSame($pick($quotation['lines']), $pick($invoice['lines']), 'Identical lines and taxes.');
        self::assertSame($converted, $this->getJson('/api/v1/quotations/'.$quotation['id']));
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices')['total']);
        $this->em()->clear();
        self::assertSame([], $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company]), 'Still nothing in the books: the draft invoice posts nothing.');
    }

    public function testTheDraftInvoiceIsEmittedLikeAnyOther(): void
    {
        $quotation = $this->richQuotation();
        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);
        $invoice = $this->getJson('/api/v1/sales-invoices/'.$converted['converted_invoice_id']);

        $payload = [
            'tercero_id' => $invoice['tercero_id'], 'contact_id' => null, 'seller_id' => $invoice['seller_id'], 'issue_date' => $invoice['issue_date'], 'notes' => $invoice['notes'],
            'lines' => array_map(static fn (array $l) => array_intersect_key($l, array_flip(['product_id', 'description', 'quantity', 'unit_price', 'discount', 'charge_tax_id', 'withholding_tax_id'])), $invoice['lines']),
            'payments' => [$this->cash($invoice['net_total'])],
        ];
        $this->sendJson('PUT', '/api/v1/sales-invoices/'.$invoice['id'], $payload);
        self::assertResponseIsSuccessful();
        $emitted = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/emit', []);

        self::assertResponseIsSuccessful();
        self::assertSame(['FE-1', $quotation['id']], [$emitted['number'], $emitted['quotation_id']]);
    }

    public function testAQuotationConvertsOnce(): void
    {
        $quotation = $this->richQuotation();
        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        $body = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('quotation_already_converted', $body['error']);
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices')['total'], '§9 Q17: one invoice only.');
    }

    public function testAnAcceptedQuotationNotConvertedYetConverts(): void
    {
        $quotation = $this->richQuotation();
        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/accept', []);

        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        self::assertResponseIsSuccessful();
        self::assertNotNull($converted['converted_invoice_id']);
    }

    public function testOnlyAnEmittedOrExpiredQuotationConverts(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->quotationDraft($this->quotationPayload($client, $product));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409, 'A draft is not offered yet.');
        self::assertSame('quotation_not_open', $this->body()['error']);

        $rejected = $this->emittedQuotation($client, $this->service('R'));
        $this->sendJson('POST', '/api/v1/quotations/'.$rejected['id'].'/reject', []);
        $this->sendJson('POST', '/api/v1/quotations/'.$rejected['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409);

        $voided = $this->emittedQuotation($client, $this->service('V'));
        $this->sendJson('POST', '/api/v1/quotations/'.$voided['id'].'/void', ['reason' => 'x']);
        $this->sendJson('POST', '/api/v1/quotations/'.$voided['id'].'/convert', []);
        self::assertResponseStatusCodeSame(409);

        $expired = $this->emittedQuotation($client, $this->service('X'));
        $this->expireOn($expired['id'], self::today('-1 day'));
        $this->sendJson('POST', '/api/v1/quotations/'.$expired['id'].'/convert', []);
        self::assertResponseIsSuccessful('An expired offer is still converted (decided 2026-10-04).');
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices')['total']);
    }

    public function testAProductDeactivatedSinceIsRefusedAndNothingIsConverted(): void
    {
        $client = $this->client();
        $product = $this->service('VIEJO');
        $quotation = $this->emittedQuotation($client, $product);
        $this->sendJson('POST', "/api/v1/products/$product/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        self::assertSame('lines.0.product_id', $body['violations'][0]['field']);
        self::assertSame('Este producto está inactivo.', $body['violations'][0]['message']);
        self::assertSame(['emitted', null], [$this->getJson('/api/v1/quotations/'.$quotation['id'])['status'], $this->body()['converted_invoice_id']], 'The quotation is untouched.');
        self::assertSame(0, $this->getJson('/api/v1/sales-invoices')['total'], 'No invoice was made.');
    }

    public function testATaxDeactivatedSinceIsRefused(): void
    {
        $client = $this->client();
        $product = $this->service('IVA', '100000', ['charge_tax_id' => null]);
        $five = $this->taxId('IVA 5 %');
        $draft = $this->quotationDraft($this->quotationPayload($client, $product, ['lines' => [$this->line($product, ['charge_tax_id' => $five])]]));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        $this->sendJson('POST', "/api/v1/taxes/$five/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('lines.0.charge_tax_id', $body['violations'][0]['field']);
        self::assertSame('Elige un impuesto cargo activo (IVA, impoconsumo).', $body['violations'][0]['message']);
        self::assertSame('emitted', $this->getJson('/api/v1/quotations/'.$draft['id'])['status']);

        // The quotation is still valid: the person fixes nothing on it (it is frozen) and duplicates it instead.
        $copy = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('draft', $copy['status']);
    }

    public function testAClientDeactivatedSinceIsRefused(): void
    {
        $client = $this->client();
        $quotation = $this->emittedQuotation($client);
        $this->sendJson('POST', "/api/v1/terceros/$client/deactivate", []);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_id', $body['violations'][0]['field']);
    }
}
