<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Domain\Model\SalesInvoice;
use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Drafts (§4.6), the list (§4.15), duplicate, the PDF, who may write (§8) and tenancy (AC-10).
 */
final class SalesInvoiceApiTest extends ApiTestCase
{
    use SalesInvoiceFixtures;
    use WithholdingTaxes;

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

    public function testATaxNotInForceOnTheInvoicesDateIsNotNewlyChosenButADraftKeepsItsOwn(): void
    {
        $client = $this->client();
        $product = $this->service();
        $five = $this->taxId('IVA 5 %');
        $payload = $this->payload($client, $product, ['lines' => [$this->line($product, ['charge_tax_id' => $five])], 'payments' => [$this->cash('1050000.00')]]);
        $kept = $this->draft($payload);
        $this->taxInForceFrom($five, '2099-01-01');

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $payload);

        self::assertResponseStatusCodeSame(422, 'F5: a tax is chosen only on a date it is in force.');
        self::assertSame(['lines.0.charge_tax_id'], array_column($body['violations'], 'field'));
        self::assertSame('Este impuesto no está vigente en la fecha del documento.', $body['violations'][0]['message']);

        $this->sendJson('PUT', '/api/v1/sales-invoices/'.$kept['id'], $payload);
        self::assertResponseIsSuccessful('A draft that already had the tax keeps it.');
        $emitted = $this->sendJson('POST', '/api/v1/sales-invoices/'.$kept['id'].'/emit', []);
        self::assertResponseIsSuccessful('And it is emitted with its snapshot.');
        self::assertSame('IVA 5 %', $emitted['lines'][0]['charge_tax_name']);
    }

    public function testALineCarriesOneRetencionPerKindEachWithItsBaseAndShare(): void
    {
        $client = $this->client();
        $product = $this->service();
        $retenciones = self::withholdings($this->taxId('ReteFuente compras 2,5 %'), $this->reteIca(), $this->taxId('ReteIVA 15 %'));

        $invoice = $this->draft($this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholdings' => $retenciones])],
            'payments' => [$this->cash('1126840.00')],
        ]));

        self::assertSame(['63160.00', '1126840.00'], [$invoice['withholding_total'], $invoice['net_total']], 'Observations PRD §4.2 / OBS-2: 25.000 + 9.660 + 28.500.');
        $line = $invoice['lines'][0];
        self::assertSame([
            ['ReteFuente compras 2,5 %', 'retefuente', '2.5000', '1000000.00', '25000.00'],
            ['ReteICA 0,966 %', 'reteica', '0.9660', '1000000.00', '9660.00'],
            ['ReteIVA 15 %', 'reteiva', '15.0000', '190000.00', '28500.00'],
        ], array_map(static fn (array $w) => [$w['name'], $w['kind'], $w['rate'], $w['base_amount'], $w['amount']], $line['withholdings']), 'Each retención is snapshotted with its base (the subtotal, or the IVA for ReteIVA) and its share, in the order sent.');
        self::assertSame('63160.00', $line['withholding_amount'], 'The line keeps the sum of its retenciones.');
        self::assertSame('ReteFuente compras 2,5 %', $line['withholding_tax_name'], 'The deprecated single field reads the first retención.');
        self::assertSame(['25000.00', '9660.00', '28500.00'], array_column($invoice['withholdings_by_tax'], 'amount'));

        $this->em()->clear();
        $model = $this->em()->find(SalesInvoice::class, Uuid::fromString($invoice['id'])) ?? self::fail('No invoice.');
        $html = static::getContainer()->get('twig')->render(SalesInvoicePdf::TEMPLATE, static::getContainer()->get(SalesInvoicePdf::class)->context($model));
        foreach (['ReteFuente compras 2,5 % <span class="muted">(base $ 1.000.000,00)</span></td><td class="num">-$ 25.000,00', 'ReteICA 0,966 % <span class="muted">(base $ 1.000.000,00)</span></td><td class="num">-$ 9.660,00', 'ReteIVA 15 % <span class="muted">(base $ 190.000,00)</span></td><td class="num">-$ 28.500,00'] as $row) {
            self::assertStringContainsString($row, $html, 'The PDF lists every retención with its own base (the IVA for ReteIVA).');
        }

        $rewritten = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$invoice['id'], $this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholdings' => self::withholdings($this->taxId('ReteIVA 15 %'))])],
            'payments' => [$this->cash('1161500.00')],
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame(['ReteIVA 15 %'], array_column($rewritten['lines'][0]['withholdings'], 'name'), 'A rewrite replaces the retenciones.');
    }

    public function testWithholdingsDecideOverTheDeprecatedSingleField(): void
    {
        $client = $this->client();
        $product = $this->service();

        $invoice = $this->draft($this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %'), 'withholdings' => []])],
        ]));

        self::assertSame([], $invoice['lines'][0]['withholdings'], 'An empty list is "no retención", whatever withholding_tax_id says.');
        self::assertNull($invoice['lines'][0]['withholding_tax_id']);
    }

    public function testTheSameKindTwiceOnALineIsRefused(): void
    {
        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($this->client(), $product = $this->service(), [
            'lines' => [$this->line($product, ['withholdings' => self::withholdings($this->taxId('ReteFuente servicios 4 %'), $this->taxId('ReteFuente compras 2,5 %'))])],
        ]));

        self::assertResponseStatusCodeSame(422, 'Observations PRD Q-G: one retención per kind on each line.');
        self::assertSame(['lines.0.withholdings'], array_column($body['violations'], 'field'));
        self::assertSame('Una retención por tipo en cada línea.', $body['violations'][0]['message']);
    }

    public function testEachRetencionMustBeAnActiveWithholdingTaxInForce(): void
    {
        $client = $this->client();
        $product = $this->service();
        $reteIva = $this->taxId('ReteIVA 15 %');
        $this->taxInForceFrom($reteIva, '2099-01-01');

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholdings' => self::withholdings($this->taxId('ReteFuente servicios 4 %'), $this->taxId('IVA 19 %'), $reteIva)])],
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['lines.0.withholdings.1.tax_id', 'lines.0.withholdings.2.tax_id'], array_column($body['violations'], 'field'), 'An IVA is no retención; a retención not in force on the date is not newly chosen.');

        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product, ['lines' => [$this->line($product, ['withholdings' => [['tax_id' => '']]])]]));
        self::assertResponseStatusCodeSame(422, 'Each retención names its tax.');
        self::assertSame(['lines[0].withholdings[0].tax_id'], array_column($body['violations'], 'field'));
    }

    public function testADraftKeepsTheSnapshotOfARetencionDeactivatedSince(): void
    {
        $client = $this->client();
        $product = $this->service();
        $reteIva = $this->taxId('ReteIVA 15 %');
        $payload = $this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholdings' => self::withholdings($this->taxId('ReteFuente servicios 4 %'), $reteIva)])],
            'payments' => [$this->cash('1121500.00')],
        ]);
        $kept = $this->draft($payload);
        $this->sendJson('POST', "/api/v1/taxes/$reteIva/deactivate", []);
        self::assertResponseIsSuccessful();

        $this->sendJson('POST', '/api/v1/sales-invoices', $payload);
        self::assertResponseStatusCodeSame(422, 'A deactivated retención is not newly chosen.');

        $rewritten = $this->sendJson('PUT', '/api/v1/sales-invoices/'.$kept['id'], $payload);
        self::assertResponseIsSuccessful('The draft that had it keeps it.');
        self::assertSame($kept['lines'][0]['withholdings'][1]['name'], $rewritten['lines'][0]['withholdings'][1]['name']);
        $emitted = $this->sendJson('POST', '/api/v1/sales-invoices/'.$kept['id'].'/emit', []);
        self::assertResponseIsSuccessful('And it is emitted with its snapshot: '.json_encode($emitted));
        self::assertSame(['40000.00', '28500.00'], array_column($emitted['lines'][0]['withholdings'], 'amount'));
    }

    public function testDuplicateCopiesEveryRetencion(): void
    {
        $client = $this->client();
        $product = $this->service();
        $source = $this->draft($this->payload($client, $product, [
            'lines' => [$this->line($product, ['withholdings' => self::withholdings($this->taxId('ReteFuente servicios 4 %'), $this->reteIca(), $this->taxId('ReteIVA 15 %'))])],
        ]));

        $copy = $this->sendJson('POST', '/api/v1/sales-invoices/'.$source['id'].'/duplicate', []);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($source['lines'][0]['withholdings'], array_map(static fn (array $w) => $w, $copy['lines'][0]['withholdings']), 'Every retención, with its snapshot, base and share.');
        self::assertSame($source['withholding_total'], $copy['withholding_total']);
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

    public function testAnEmptyDueDateIsNoDueDateNotToday(): void
    {
        $credit = ['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1190000.00', 'due_date' => ''];
        $body = $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($this->client(), $this->service(), ['payments' => [$credit]]));

        self::assertResponseStatusCodeSame(422, 'An empty due date is missing, not today: a crédito line needs one.');
        self::assertContains('payments.0.due_date', array_column($body['violations'] ?? [], 'field'), json_encode($body) ?: '');
    }

    public function testTheAccountantWritesSalesInvoices(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->draft($this->payload($client, $product));
        $this->signInAs(Role::Accountant);

        // §8 as changed on 2026-10-04: the accountant writes every document.
        $this->sendJson('POST', '/api/v1/sales-invoices', $this->payload($client, $product));
        self::assertResponseStatusCodeSame(201);
        $this->sendJson('PUT', '/api/v1/sales-invoices/'.$draft['id'], $this->payload($client, $product));
        self::assertResponseIsSuccessful();
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/duplicate', []);
        self::assertResponseIsSuccessful();
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
