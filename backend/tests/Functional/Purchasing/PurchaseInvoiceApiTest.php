<?php

namespace App\Tests\Functional\Purchasing;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * §4.10, §4.15 and §6: drafts of facturas de compra (lines by product or by expense account), the list, duplicate, the
 * PDF and the supplier's file; who may do what (§8); and another company's invoice is a 404 everywhere.
 */
final class PurchaseInvoiceApiTest extends ApiTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    /** @return array<mixed> */
    private function upload(string $id, string $name, string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, $content);
        $this->client->request('POST', "/api/v1/purchase-invoices/$id/attachments", files: ['file' => new UploadedFile($path, $name, null, null, true)], server: ['HTTP_ACCEPT' => 'application/json']);

        return $this->body();
    }

    private static function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    public function testTheOwnerSavesADraftAndReadsItBack(): void
    {
        $draft = $this->createDraft();

        self::assertSame('draft', $draft['status']);
        self::assertNull($draft['number'], 'The internal number is taken at emission.');
        self::assertSame('Servicios Andinos S.A.S.', $draft['tercero_name']);
        self::assertSame('FAC-881', $draft['supplier_invoice_number']);
        self::assertSame(self::today(-1), $draft['issue_date']);
        self::assertSame(self::today(29), $draft['due_date']);
        self::assertSame(['1000000.00', '0.00', '1000000.00', '190000.00', '40000.00', '1150000.00'], [$draft['gross_total'], $draft['discount_total'], $draft['subtotal'], $draft['tax_total'], $draft['withholding_total'], $draft['net_total']]);
        self::assertSame('1150000.00', $draft['balance']);
        $line = $draft['lines'][0];
        self::assertNull($line['product_id']);
        self::assertSame('513595 · OTROS', $line['account_label'], 'The account as the editor shows it.');
        self::assertSame('IVA 19 %', $line['charge_tax_name']);
        self::assertSame('ReteFuente servicios 4 %', $line['withholding_tax_name']);
        self::assertSame('1150000.00', $line['total_amount']);
        self::assertSame([['method_name' => 'Crédito', 'kind' => 'credit', 'amount' => '1150000.00', 'due_date' => self::today(29)]], array_map(static fn (array $p) => array_intersect_key($p, ['kind' => 1, 'method_name' => 1, 'amount' => 1, 'due_date' => 1]), $draft['payments']));
        self::assertSame([], $draft['payables'], 'A draft owes nothing yet.');
        self::assertSame($draft, $this->getJson("/api/v1/purchase-invoices/{$draft['id']}"));
    }

    public function testALineByProductPostsLaterToTheProductAndKeepsItsLabel(): void
    {
        $product = $this->sendJson('POST', '/api/v1/products', ['type' => 'producto', 'code' => 'RES-01', 'name' => 'Resma carta', 'sale_price' => '20000', 'price_includes_tax' => false]);
        self::assertResponseStatusCodeSame(201);

        $draft = $this->createDraft(['lines' => [[
            'product_id' => $product['id'], 'account_id' => null, 'description' => 'Resma carta', 'quantity' => '10', 'unit_price' => '15000', 'discount' => '10',
            'charge_tax_id' => null, 'withholding_tax_id' => null,
        ]], 'payments' => [['payment_method_id' => $this->methodId('Efectivo'), 'amount' => '135000.00', 'due_date' => null]]]);

        self::assertSame('RES-01 · Resma carta', $draft['lines'][0]['product_label']);
        self::assertSame('135000.00', $draft['net_total'], 'Discount netted: 10 × 15 000 − 10 %.');
        self::assertSame('Ninguno', $draft['lines'][0]['charge_tax_name'], 'No tax is "Ninguno".');
    }

    public function testATaxNotInForceOnTheInvoicesDateIsNotNewlyChosenButADraftKeepsItsOwn(): void
    {
        $five = $this->taxId('IVA 5 %');
        $lines = $this->payload()['lines'];
        $lines[0]['charge_tax_id'] = $five;
        $payload = ['lines' => $lines, 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1010000.00', 'due_date' => self::today(29)]]];
        $kept = $this->createDraft($payload);
        $this->taxInForceFrom($five, '2099-01-01');

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload($payload + ['supplier_invoice_number' => 'FAC-882']));

        self::assertResponseStatusCodeSame(422, 'F5: a tax is chosen only on a date it is in force.');
        self::assertSame(['lines[0].charge_tax_id'], self::violationFields($body));
        self::assertSame('Este impuesto no está vigente en la fecha del documento.', $body['violations'][0]['message']);

        $this->sendJson('PUT', "/api/v1/purchase-invoices/{$kept['id']}", $this->payload($payload));
        self::assertResponseIsSuccessful('A draft that already had the tax keeps it.');
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$kept['id']}/emit", []);
        self::assertResponseIsSuccessful('And it is emitted.');
    }

    public function testADraftMayWaitForTheSupplierNumber(): void
    {
        $draft = $this->createDraft(['supplier_invoice_number' => '  ']);

        self::assertNull($draft['supplier_invoice_number']);
    }

    public function testUpdatingADraftReplacesItsContents(): void
    {
        $draft = $this->createDraft();
        $payload = $this->payload(['supplier_invoice_number' => 'FAC-900', 'notes' => null]);
        $payload['lines'][] = $payload['lines'][0];
        $payload['payments'][0]['amount'] = '2300000.00';

        $body = $this->sendJson('PUT', "/api/v1/purchase-invoices/{$draft['id']}", $payload);

        self::assertResponseIsSuccessful(json_encode($body) ?: '');
        self::assertSame('FAC-900', $body['supplier_invoice_number']);
        self::assertCount(2, $body['lines']);
        self::assertSame('2300000.00', $body['net_total']);
        self::assertNull($body['notes']);
    }

    public function testALineHasAProductOrAnAccountNotBothAndNotNeither(): void
    {
        $payload = $this->payload();
        $payload['lines'][1] = $payload['lines'][0];
        $payload['lines'][1]['account_id'] = null;

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['lines[1].account_id'], self::violationFields($body));
    }

    public function testOnlyAccountsUsableOnPurchasesMayBeChosen(): void
    {
        $payload = $this->payload();
        $payload['lines'][0]['account_id'] = $this->account('11050501')->toRfc4122();

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $payload);

        self::assertResponseStatusCodeSame(422, '§9 Q14: classes 5, 6, 7 or an account marked usable on purchases.');
        self::assertSame(['lines[0].account_id'], self::violationFields($body));
    }

    public function testTaxesMustBeOfTheirClassAndMethodsOfTheCompany(): void
    {
        $payload = $this->payload();
        $payload['lines'][0]['charge_tax_id'] = $this->taxId('ReteFuente servicios 4 %', 'withholding');
        $payload['payments'][0]['payment_method_id'] = '0192f5a0-0000-7000-8000-000000000000';

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['lines[0].charge_tax_id', 'payments[0].payment_method_id'], self::violationFields($body));
    }

    public function testACreditPaymentNeedsADueDateNotBeforeTheInvoice(): void
    {
        $payload = $this->payload(['due_date' => null]);
        $payload['payments'][0]['due_date'] = null;
        $this->sendJson('POST', '/api/v1/purchase-invoices', $payload);
        self::assertResponseStatusCodeSame(422);

        $payload['payments'][0]['due_date'] = self::today(-5);
        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $payload);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payments[0].due_date'], self::violationFields($body));
    }

    public function testTheShapeIsCheckedFirst(): void
    {
        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', ['tercero_id' => 'nope', 'issue_date' => '03/10/2026', 'lines' => [['description' => '', 'quantity' => '-1', 'unit_price' => 'x']], 'payments' => []]);

        self::assertResponseStatusCodeSame(422);
        $fields = self::violationFields($body);
        foreach (['tercero_id', 'issue_date', 'lines[0].quantity', 'lines[0].unit_price'] as $field) {
            self::assertContains($field, $fields);
        }
    }

    public function testAnUnknownSupplierIsAViolation(): void
    {
        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload(['tercero_id' => '0192f5a0-0000-7000-8000-000000000000']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['tercero_id'], self::violationFields($body));
    }

    public function testTheSupplierNumberIsUniquePerSupplier(): void
    {
        $this->createDraft();

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload(['supplier_invoice_number' => 'fac-881']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('duplicate_supplier_invoice_number', $body['error']);
        self::assertSame(['supplier_invoice_number'], self::violationFields($body));
        self::assertSame('Ya registraste una factura de este proveedor con este número.', $body['violations'][0]['message'], 'In Spanish, next to the field.');

        $other = $this->tercero('Papelería Central');
        $this->createDraft(['tercero_id' => $other->toRfc4122()]);
    }

    public function testTheListSearchesFiltersAndPaginates(): void
    {
        $a = $this->emitInvoice(['supplier_invoice_number' => 'A-1']);
        $this->createDraft(['supplier_invoice_number' => 'B-2', 'issue_date' => self::today(-10), 'due_date' => null, 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1150000.00', 'due_date' => self::today(20)]]]);
        $other = $this->tercero('Papelería Central');
        $this->createDraft(['tercero_id' => $other->toRfc4122(), 'supplier_invoice_number' => '100%_x']);

        $all = $this->getJson('/api/v1/purchase-invoices');
        self::assertSame(3, $all['total']);
        self::assertSame(['id', 'status', 'number', 'tercero_id', 'tercero_name', 'supplier_invoice_number', 'issue_date', 'due_date', 'net_total', 'paid_amount', 'balance'], array_keys($all['items'][0]));

        $byNumber = fn (string $query) => array_column($this->getJson('/api/v1/purchase-invoices?'.$query)['items'], 'supplier_invoice_number');
        self::assertSame(['A-1'], $byNumber('q=FC-1'), 'By the internal number.');
        self::assertSame(['B-2'], $byNumber('q=b-2'), 'By the supplier\'s number.');
        self::assertSame(['100%_x'], $byNumber('q=papeler'), 'By the supplier\'s name.');
        self::assertSame(['100%_x'], $byNumber('q=0%25_'), '% and _ match literally.');
        self::assertSame(['A-1'], $byNumber('status=emitted'));
        self::assertSame(['B-2'], $byNumber('to='.self::today(-5)));
        self::assertCount(2, $byNumber('from='.self::today(-5)));

        $page = $this->getJson('/api/v1/purchase-invoices?per_page=2&page=2');
        self::assertCount(1, $page['items']);
        self::assertSame(2, $page['page']);
        self::assertSame($a['id'], $this->getJson('/api/v1/purchase-invoices?status=emitted')['items'][0]['id']);
    }

    public function testDuplicatingMakesANewDraft(): void
    {
        $invoice = $this->emitInvoice();

        $copy = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/duplicate", []);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('draft', $copy['status']);
        self::assertNotSame($invoice['id'], $copy['id']);
        self::assertNull($copy['supplier_invoice_number']);
        self::assertSame(self::today(), $copy['issue_date']);
        self::assertSame($invoice['net_total'], $copy['net_total']);
    }

    public function testADraftCanBeDeletedButNotAnEmittedInvoice(): void
    {
        $draft = $this->createDraft();
        $this->client->request('DELETE', "/api/v1/purchase-invoices/{$draft['id']}");
        self::assertResponseStatusCodeSame(204);
        $this->getJson("/api/v1/purchase-invoices/{$draft['id']}");
        self::assertResponseStatusCodeSame(404);

        $invoice = $this->emitInvoice();
        $this->client->request('DELETE', "/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertResponseStatusCodeSame(409);
    }

    public function testTheSuppliersPdfIsAttachedListedDownloadedAndRemoved(): void
    {
        $draft = $this->createDraft();

        $file = $this->upload($draft['id'], 'factura-881.pdf', self::pdfBytes());
        self::assertResponseStatusCodeSame(201, json_encode($file) ?: '');
        self::assertSame('factura-881.pdf', $file['file_name']);
        self::assertSame('application/pdf', $file['content_type']);

        $xml = $this->upload($draft['id'], 'factura.xml', '<?xml version="1.0"?><Invoice><ID>FAC-881</ID></Invoice>');
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['factura-881.pdf', 'factura.xml'], array_column($this->getJson("/api/v1/purchase-invoices/{$draft['id']}")['attachments'], 'file_name'));

        $this->client->request('GET', "/api/v1/purchase-invoices/{$draft['id']}/attachments/{$file['id']}");
        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('DELETE', "/api/v1/purchase-invoices/{$draft['id']}/attachments/{$xml['id']}");
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->getJson("/api/v1/purchase-invoices/{$draft['id']}")['attachments']);
    }

    public function testOnlyAPdfOrAnXmlOfAtMostTenMegabytesIsAccepted(): void
    {
        $draft = $this->createDraft();

        $body = $this->upload($draft['id'], 'factura.pdf', "\x89PNG\r\n\x1a\nnot really");
        self::assertResponseStatusCodeSame(415, 'Judged by the bytes, not the name.');
        self::assertSame('attachment_unsupported', $body['error']);

        $this->upload($draft['id'], 'grande.pdf', self::pdfBytes().str_repeat('0', 10 * 1024 * 1024));
        self::assertResponseStatusCodeSame(413);
    }

    public function testAnEmittedInvoiceKeepsItsFiles(): void
    {
        $draft = $this->createDraft();
        $file = $this->upload($draft['id'], 'factura.pdf', self::pdfBytes());
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        $this->client->request('DELETE', "/api/v1/purchase-invoices/{$draft['id']}/attachments/{$file['id']}");
        self::assertResponseStatusCodeSame(409);
        $this->upload($draft['id'], 'otra.pdf', self::pdfBytes());
        self::assertResponseStatusCodeSame(409);
    }

    public function testThePdfIsTheInternalRecordOfThePurchase(): void
    {
        $invoice = $this->emitInvoice();

        $this->client->request('GET', "/api/v1/purchase-invoices/{$invoice['id']}/pdf");

        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('FC-1', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testTheAccountantReadsAndWrites(): void
    {
        $draft = $this->createDraft();
        $this->signInAs('accountant');

        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total']);
        // §8 as changed on 2026-10-04: the accountant writes every document.
        $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload(['supplier_invoice_number' => 'X-1']));
        self::assertResponseStatusCodeSame(201);
        $this->sendJson('PUT', "/api/v1/purchase-invoices/{$draft['id']}", $this->payload());
        self::assertResponseIsSuccessful();
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/duplicate", []);
        self::assertResponseIsSuccessful();
        $this->upload($draft['id'], 'factura.pdf', self::pdfBytes());
        self::assertResponseIsSuccessful();
    }

    public function testAnotherCompanysInvoiceIsNotFound(): void
    {
        $draft = $this->createDraft();
        $file = $this->upload($draft['id'], 'factura.pdf', self::pdfBytes());
        $this->signOut();
        $this->startPurchasing('bea@otra.co', '900765432', 'Otra S.A.S.');

        $id = $draft['id'];
        $this->getJson("/api/v1/purchase-invoices/$id");
        self::assertResponseStatusCodeSame(404, 'Acceptance criterion 10: another company\'s id is not found.');
        self::assertSame(0, $this->getJson('/api/v1/purchase-invoices')['total'], 'Nor listed.');
        foreach ([['PUT', "/$id"], ['POST', "/$id/emit"], ['POST', "/$id/void"], ['POST', "/$id/duplicate"], ['DELETE', "/$id"], ['DELETE', "/$id/attachments/{$file['id']}"]] as [$method, $path]) {
            $this->sendJson($method, '/api/v1/purchase-invoices'.$path, 'PUT' === $method ? $this->payload() : ['reason' => 'x']);
            self::assertResponseStatusCodeSame(404, "$method $path");
        }
        foreach (["/$id/pdf", "/$id/attachments/{$file['id']}", '/not-an-id'] as $path) {
            $this->client->request('GET', '/api/v1/purchase-invoices'.$path);
            self::assertResponseStatusCodeSame(404, "GET $path");
        }
        $this->upload($id, 'factura.pdf', self::pdfBytes());
        self::assertResponseStatusCodeSame(404);
    }
}
