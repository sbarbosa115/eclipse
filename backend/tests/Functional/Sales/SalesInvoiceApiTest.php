<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Tests\Support\ApiTestCase;

/**
 * Drafts (§4.6), the list (§4.15), duplicate, the PDF, who may write (§8) and tenancy (AC-10).
 */
final class SalesInvoiceApiTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testADraftIsSavedWithItsLinesTaxesAndPayments(): void
    {
        $client = $this->client();
        $product = $this->service();

        $invoice = $this->draft($this->payload($client, $product, [
            'notes' => 'Entregar en la oficina',
            'lines' => [$this->line($product, ['quantity' => '2', 'discount' => '10', 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %')])],
            'payments' => [$this->cash('500000.00'), $this->credit('100.00', self::today('+30 days'))],
        ]));

        self::assertSame('draft', $invoice['status']);
        self::assertNull($invoice['number']);
        self::assertSame('Cliente Uno S.A.S.', $invoice['tercero_name'], 'The client\'s name is copied.');
        self::assertSame('Entregar en la oficina', $invoice['notes']);
        self::assertSame(['2000000.00', '200000.00', '1800000.00', '342000.00', '72000.00', '2070000.00'], [$invoice['gross_total'], $invoice['discount_total'], $invoice['subtotal'], $invoice['tax_total'], $invoice['withholding_total'], $invoice['net_total']]);
        $line = $invoice['lines'][0];
        self::assertSame('CONS · Consultoría', $line['product_label']);
        self::assertSame(['IVA 19 %', '19.0000', 'percentage'], [$line['charge_tax_name'], $line['charge_tax_rate'], $line['charge_tax_calculation']], 'The tax is copied onto the line.');
        self::assertSame('ReteFuente servicios 4 %', $line['withholding_tax_name']);
        self::assertSame(['2.0000', '1000000.0000', '10.0000'], [$line['quantity'], $line['unit_price'], $line['discount']]);
        self::assertSame(['cash', 'credit'], array_column($invoice['payments'], 'kind'));
        self::assertSame([null, self::today('+30 days')], array_column($invoice['payments'], 'due_date'));
        self::assertSame('0.00', $invoice['balance'], 'A draft is owed by nobody.');
        self::assertSame([], $invoice['receivables']);
    }

    public function testADraftIsRewrittenWhole(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->draft($this->payload($client, $product));

        $invoice = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$draft['id'], $this->payload($client, $product, [
            'issue_date' => self::today('-3 days'),
            'lines' => [$this->line($product, ['unit_price' => '500', 'charge_tax_id' => null]), $this->line($product, ['unit_price' => '250', 'charge_tax_id' => null])],
            'payments' => [],
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(self::today('-3 days'), $invoice['issue_date']);
        self::assertCount(2, $invoice['lines']);
        self::assertSame([1, 2], array_column($invoice['lines'], 'position'));
        self::assertSame('750.00', $invoice['net_total']);
        self::assertSame('Ninguno', $invoice['lines'][0]['charge_tax_name'], 'Null is "sin impuesto".');
        self::assertSame([], $invoice['payments']);
    }

    public function testEveryProblemIsReportedByItsField(): void
    {
        $client = $this->client();
        $product = $this->service();

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product, [
            'lines' => [
                $this->line($product, ['quantity' => '0', 'discount' => '120']),
                $this->line($product, ['charge_tax_id' => $this->taxId('ReteFuente servicios 4 %')]),
            ],
            'payments' => [$this->credit('100.00', self::today('-40 days'))],
        ]));

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('lines[0].quantity', $fields);
        self::assertContains('lines[0].discount', $fields);

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product, [
            'lines' => [$this->line($product, ['charge_tax_id' => $this->taxId('ReteFuente servicios 4 %')])],
            'payments' => [$this->credit('100.00', self::today('-40 days'))],
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['lines.0.charge_tax_id', 'payments.0.due_date'], array_column($body['violations'], 'field'), 'A retención is not an impuesto cargo; a crédito is not due before the invoice.');
    }

    public function testReferencesMustBeTheCompanysOwn(): void
    {
        $product = $this->service();
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');
        $client = $this->client();

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product));

        self::assertResponseStatusCodeSame(422, 'Another company\'s product is not one of ours.');
        self::assertSame(['lines.0.product_id'], array_column($body['violations'], 'field'));
    }

    public function testAnInactiveClientIsNotChosen(): void
    {
        $client = $this->client();
        $this->sendJson('POST', "/api/v1/terceros/$client/deactivate", []);

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $this->service()));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_id', $body['violations'][0]['field']);
    }

    public function testTheListFiltersByTextStatusAndDate(): void
    {
        $andina = $this->client('Distribuciones Andina', number: '890903938');
        $emitted = $this->emitted(client: $andina);
        $this->draft($this->payload($this->client('Cliente Sur', number: '860002964'), $this->service('B'), ['issue_date' => self::today('-20 days')]));

        $all = $this->getJson('/api/v1/sales-invoices');
        self::assertSame(2, $all['total']);
        self::assertSame('Distribuciones Andina', $all['items'][0]['tercero_name'], 'Newest first.');
        $row = $all['items'][0];
        self::assertSame(['FE-1', 'paid', '1190000.00', '0.00'], [$row['number'], $row['status'], $row['net_total'], $row['balance']]);

        self::assertSame(1, $this->getJson('/api/v1/sales-invoices?q=andina')['total'], 'By the client\'s name.');
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices?q=FE-1')['total'], 'By the number.');
        self::assertSame(0, $this->getJson('/api/v1/sales-invoices?q=%25')['total'], '% is matched literally.');
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices?status=draft')['total']);
        self::assertSame($emitted['id'], $this->getJson('/api/v1/sales-invoices?status=paid')['items'][0]['id']);
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices?from='.self::today('-1 day'))['total']);
        self::assertSame(1, $this->getJson('/api/v1/sales-invoices?to='.self::today('-1 day'))['total']);
        self::assertSame(1, $this->getJson("/api/v1/sales-invoices?tercero_id=$andina")['total']);
        $page = $this->getJson('/api/v1/sales-invoices?per_page=1&page=2');
        self::assertSame([2, 1, 2], [$page['total'], $page['per_page'], $page['page']]);
        self::assertCount(1, $page['items']);

        $this->getJson('/api/v1/sales-invoices?from=03/10/2026');
        self::assertResponseStatusCodeSame(400);
    }

    public function testTheListShowsWhatIsStillOwed(): void
    {
        $this->emitted([$this->cash('190000.00'), $this->credit('1000000.00', self::today('+60 days'))]);

        $row = $this->getJson('/api/v1/sales-invoices')['items'][0];

        self::assertSame(['emitted', '1000000.00', '0.00', self::today('+60 days')], [$row['status'], $row['balance'], $row['paid_amount'], $row['due_date']]);
    }

    public function testDuplicateMakesANewDraftDatedToday(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->draft($this->payload($client, $product, ['issue_date' => self::today('-10 days'), 'payments' => [$this->credit('1190000.00', self::today('+20 days'))]]));
        $source = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        $copy = $this->sendJson('POST', '/api/v1/sales-invoices/'.$source['id'].'/duplicate', []);

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($source['id'], $copy['id']);
        self::assertSame(['draft', null, self::today()], [$copy['status'], $copy['number'], $copy['issue_date']]);
        self::assertSame($source['net_total'], $copy['net_total']);
        self::assertSame(self::today('+30 days'), $copy['payments'][0]['due_date'], 'A crédito keeps its 30-day term.');
        self::assertSame(2, $this->getJson('/api/v1/sales-invoices')['total']);
    }

    public function testThePdfIsDownloaded(): void
    {
        $invoice = $this->emitted();

        $this->client->request('GET', '/api/v1/sales-invoices/'.$invoice['id'].'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('factura-FE-1.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testTheAccountantReadsButDoesNotWrite(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->draft($this->payload($client, $product));
        $this->signInAs(Role::Accountant);

        self::assertSame(1, $this->getJson('/api/v1/sales-invoices')['total'], 'The accountant reads the list.');
        $this->getJson('/api/v1/sales-invoices/'.$draft['id']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/sales-invoices/'.$draft['id'].'/pdf');
        self::assertResponseIsSuccessful('and the PDF.');

        foreach ([
            ['POST', '/api/v1/sales-invoices', $this->payload($client, $product)],
            ['PUT', '/api/v1/sales-invoices/'.$draft['id'], $this->payload($client, $product)],
            ['POST', '/api/v1/sales-invoices/'.$draft['id'].'/duplicate', []],
            ['POST', '/api/v1/sales-invoices/'.$draft['id'].'/void', ['reason' => 'x']],
            ['POST', '/api/v1/sales-invoices/'.$draft['id'].'/send', []],
        ] as [$method, $uri, $payload]) {
            $this->sendJson($method, $uri, $payload);
            self::assertResponseStatusCodeSame(403, "§8: the accountant may not $method $uri.");
        }
    }

    public function testAnotherCompanySeesNothing(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->draft($this->payload($client, $product));
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');

        self::assertSame(0, $this->getJson('/api/v1/sales-invoices')['total'], 'AC-10: no rows of company A.');
        $mine = $this->payload($this->client('Mío', number: '811111111'), $this->service('MIO'));
        foreach ([['GET', ''], ['GET', '/pdf'], ['PUT', '']] as [$method, $suffix]) {
            $this->sendJson($method, '/api/v1/sales-invoices/'.$draft['id'].$suffix, $mine);
            self::assertResponseStatusCodeSame(404, "AC-10: $method $suffix of another company's invoice.");
        }
        $this->getJson('/api/v1/sales-invoices/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSignedOutIsRefused(): void
    {
        $this->signOut();

        $this->getJson('/api/v1/sales-invoices');

        self::assertResponseStatusCodeSame(401);
    }
}
