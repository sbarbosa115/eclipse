<?php

namespace App\Tests\Functional\Acceptance;

use App\Sales\Application\Document\QuotationPdf;
use App\Sales\Domain\Model\Quotation;
use App\Tests\Functional\Sales\QuotationFixtures;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Observations PRD §8, OBS-5: "A quotation PDF prints the Código column; converting it carries the codes to the
 * invoice; renaming the product afterwards changes neither".
 */
final class OBS05ProductCodeOnDocumentsTest extends ApiTestCase
{
    use QuotationFixtures;

    public function testTheQuotationPrintsTheCodeAndTheInvoiceConvertedFromItCarriesIt(): void
    {
        $this->startCompany();
        $product = $this->service('SERV-10');
        $draft = $this->quotationDraft($this->quotationPayload($this->client(), $product));
        $quotation = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful();

        $this->sendJson('PUT', "/api/v1/products/$product", ['type' => 'servicio', 'code' => 'SERV-11', 'name' => 'Otro nombre', 'sale_price' => '1000000', 'price_includes_tax' => false, 'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => null]);
        self::assertResponseIsSuccessful();

        $converted = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/convert', []);
        self::assertResponseIsSuccessful();
        self::assertSame('SERV-10', $this->getJson('/api/v1/sales-invoices/'.$converted['converted_invoice_id'])['lines'][0]['product_code'], 'The invoice carries the quotation\'s code.');
        self::assertSame('SERV-10', $this->getJson('/api/v1/quotations/'.$quotation['id'])['lines'][0]['product_code'], 'The quotation keeps its own.');

        $this->em()->clear();
        $html = static::getContainer()->get('twig')->render(QuotationPdf::TEMPLATE, static::getContainer()->get(QuotationPdf::class)->context($this->em()->getRepository(Quotation::class)->find(Uuid::fromString($quotation['id'])) ?? self::fail('No quotation.')));
        self::assertStringContainsString('<td>SERV-10</td>', $html, 'The PDF prints the code the quotation was emitted with.');
        self::assertStringNotContainsString('SERV-11', $html);
    }
}
