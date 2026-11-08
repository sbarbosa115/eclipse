<?php

namespace App\Tests\Functional\Sales;

use App\Sales\Application\Document\QuotationPdf;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\SalesInvoice;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Observations PRD §4.4: a line snapshots the product's code when it is written; a product recoded or renamed later
 * changes no document, a copy (duplicate, convert) passes the source line's own snapshot on, and the PDFs print it in a
 * Código column before Descripción.
 */
final class ProductCodeSnapshotTest extends ApiTestCase
{
    use QuotationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /** Rewrites the product with a new code and name (the full form, as the product screen does). */
    private function recode(string $productId, string $code): void
    {
        $this->sendJson('PUT', "/api/v1/products/$productId", [
            'type' => 'servicio', 'code' => $code, 'name' => 'Renombrado', 'sale_price' => '1000000', 'price_includes_tax' => false,
            'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => null,
        ]);
        self::assertResponseIsSuccessful('The product is recoded.');
    }

    public function testASalesInvoiceLineKeepsTheCodeItWasWrittenWithWhenTheProductIsRecoded(): void
    {
        $client = $this->client();
        $product = $this->service('CONS-01');
        $draft = $this->draft($this->payload($client, $product));
        self::assertSame('CONS-01', $draft['lines'][0]['product_code'], 'The code is copied when the draft is saved.');

        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful();
        $this->recode($product, 'NUEVO-02');

        self::assertSame('CONS-01', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['lines'][0]['product_code'], 'An emitted invoice does not follow the product.');

        $copy = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('CONS-01', $copy['lines'][0]['product_code'], 'A copy passes the source line\'s own snapshot on, not the product\'s current code.');

        $saved = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$copy['id'], $this->payload($client, $product));
        self::assertResponseIsSuccessful();
        self::assertSame('CONS-01', $saved['lines'][0]['product_code'], 'A draft saved again keeps the code its line already has for that product (COD-03).');

        $other = $this->service('OTRO-01');
        $changed = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$copy['id'], $this->payload($client, $other));
        self::assertResponseIsSuccessful();
        self::assertSame('OTRO-01', $changed['lines'][0]['product_code'], 'Another product on the line brings its own code.');
    }

    public function testTheQuotationPdfPrintsTheCodeColumnAndConvertingCarriesTheSnapshot(): void
    {
        $client = $this->client();
        $product = $this->service('COT-01');
        $draft = $this->quotationDraft($this->quotationPayload($client, $product));
        $quotation = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful();
        self::assertSame('COT-01', $quotation['lines'][0]['product_code']);

        $this->em()->clear();
        $html = static::getContainer()->get('twig')->render(QuotationPdf::TEMPLATE, static::getContainer()->get(QuotationPdf::class)->context($this->em()->getRepository(Quotation::class)->find(Uuid::fromString($quotation['id'])) ?? self::fail('No quotation.')));
        self::assertMatchesRegularExpression('#<th>\#</th><th>Código</th><th>Descripción</th>#', $html, 'Código sits before Descripción.');
        self::assertStringContainsString('<td>COT-01</td>', $html);

        $this->recode($product, 'OTRO-9');
        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);
        self::assertResponseIsSuccessful();
        $invoice = $this->getJson('/api/v1/sales-invoices/'.$converted['converted_invoice_id']);
        self::assertSame('COT-01', $invoice['lines'][0]['product_code'], 'Converting carries the quotation line\'s code, not the product\'s today.');
        $saved = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$invoice['id'], $this->payload($client, $product));
        self::assertResponseIsSuccessful(json_encode($saved) ?: '');
        self::assertSame('COT-01', $saved['lines'][0]['product_code'], 'The converted draft keeps it when it is saved (COD-03).');

        $copy = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('COT-01', $copy['lines'][0]['product_code']);
    }

    public function testTheSalesInvoicePdfPrintsTheCode(): void
    {
        $service = $this->service('FV-77');
        $draft = $this->draft($this->payload($this->client(), $service));
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful();

        $this->em()->clear();
        $model = $this->em()->find(SalesInvoice::class, Uuid::fromString($draft['id'])) ?? self::fail('No invoice.');
        $html = static::getContainer()->get('twig')->render(SalesInvoicePdf::TEMPLATE, static::getContainer()->get(SalesInvoicePdf::class)->context($model));
        self::assertMatchesRegularExpression('#<th>\#</th><th>Código</th><th>Descripción</th>#', $html);
        self::assertStringContainsString('<td>FV-77</td>', $html);
    }
}
