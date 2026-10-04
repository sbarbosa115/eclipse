<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Tests\Support\ApiTestCase;

/**
 * Emission (§4.8): the resolution's number and the internal consecutive, frozen, one receivable per crédito line,
 * the A.1 entry; and every refusal (§4.6 payments, §9 Q11 dates, the lock date, the client, the resolution).
 */
final class EmitSalesInvoiceApiTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testEmissionNumbersPostsAndFreezesTheInvoice(): void
    {
        $client = $this->client();
        $draft = $this->draft($this->payload($client, $this->service(), ['payments' => [$this->cash('190000.00'), $this->credit('1000000.00', self::today('+15 days'))]]));
        self::assertNull($draft['number']);

        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseIsSuccessful();
        self::assertSame('emitted', $invoice['status']);
        self::assertSame('FE-1', $invoice['number'], 'The resolution\'s first number, with its prefix.');
        self::assertSame(1, $invoice['authorised_number']);
        self::assertSame(1, $invoice['internal_number'], '§4.8: the internal consecutive too.');
        self::assertNotNull($invoice['emitted_at']);
        self::assertNotNull($invoice['journal_entry_id'], 'The entry it owns (§3).');
        self::assertSame('1000000.00', $invoice['balance'], 'The crédito part is owed.');
        self::assertCount(1, $invoice['receivables']);
        self::assertSame(['due_date' => self::today('+15 days'), 'amount' => '1000000.00', 'balance' => '1000000.00', 'voided' => false], array_diff_key($invoice['receivables'][0], ['id' => 1]));

        [$entry] = $this->entries();
        self::assertSame([
            ['11050501', '190000.00', '0.00'],
            ['13050501', '1000000.00', '0.00'],
            ['413595', '0.00', '1000000.00'],
            ['240805', '0.00', '190000.00'],
        ], self::movements($entry), 'A.1: caja and 1305 against ingresos and IVA generado.');
        self::assertSame(['sales_invoice', 'FE-1'], [$entry->sourceType(), $entry->sourceNumber()]);
        self::assertSame($invoice['journal_entry_id'], $entry->id()->toRfc4122());

        $this->sendJson('PUT', '/api/v1/sales-invoices/'.$draft['id'], $this->payload($client, $this->service('OTRO')));
        self::assertResponseStatusCodeSame(409, 'Emitted: immutable (§4.6).');
        self::assertSame('document_not_draft', $this->body()['error']);
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseStatusCodeSame(409, 'Emitted once.');
        self::assertCount(1, $this->entries());
    }

    public function testNumbersFollowEachOther(): void
    {
        $first = $this->emitted();
        $second = $this->emitted(client: $this->client('Cliente Dos', number: '890903938'));

        self::assertSame(['FE-1', 'FE-2'], [$first['number'], $second['number']]);
        self::assertSame([1, 2], [$first['internal_number'], $second['internal_number']]);
    }

    public function testAProductsOwnRevenueAccountIsCredited(): void
    {
        $services = $this->accountId('415595');
        $product = $this->service('PROPIA', '100000', ['charge_tax_id' => null]);
        $this->sendJson('PUT', "/api/v1/products/$product", [
            'type' => 'servicio', 'code' => 'PROPIA', 'name' => 'Consultoría', 'sale_price' => '100000', 'price_includes_tax' => false,
            'charge_tax_id' => null, 'withholding_tax_id' => null, 'revenue_account_id' => $services,
        ]);
        self::assertResponseIsSuccessful();
        $draft = $this->draft($this->payload($this->client(), $product, ['lines' => [$this->line($product, ['unit_price' => '100000', 'charge_tax_id' => null])], 'payments' => [$this->cash('100000.00')]]));

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseIsSuccessful();
        self::assertSame([['11050501', '100000.00', '0.00'], ['415595', '0.00', '100000.00']], self::movements($this->entries()[0]), '§5: ingreso is overridable per product.');
    }

    public function testDiscountsAndWithholdingsArePostedAsA1(): void
    {
        $product = $this->service();
        $line = $this->line($product, ['quantity' => '2', 'discount' => '10', 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %')]);
        // 2 × 1.000.000 − 10 % = 1.800.000; IVA 342.000; ReteFuente 72.000 → neto 2.070.000.
        $draft = $this->draft($this->payload($this->client(), $product, ['lines' => [$line], 'payments' => [$this->credit('2070000.00')]]));

        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseIsSuccessful();
        self::assertSame(['2000000.00', '200000.00', '342000.00', '72000.00', '2070000.00'], [$invoice['gross_total'], $invoice['discount_total'], $invoice['tax_total'], $invoice['withholding_total'], $invoice['net_total']]);
        [$entry] = $this->entries();
        self::assertSame([
            ['13050501', '2070000.00', '0.00'],
            ['417501', '200000.00', '0.00'],
            ['135515', '72000.00', '0.00'],
            ['413595', '0.00', '2000000.00'],
            ['240805', '0.00', '342000.00'],
        ], self::movements($entry), 'Discount posted gross (4175), retención suffered at emission (§9 Q4).');
        self::assertSame('2342000.00', $entry->totalDebit()->toString(), 'Σ = neto + retenciones + descuentos = bruto + impuestos.');
    }

    public function testThePaymentsMustAddUpToTotalNeto(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service(), ['payments' => [$this->cash('1000000.00')]]));

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('payments_do_not_match_total', $body['error']);
        self::assertSame(['payments_total' => '1000000.00', 'net_total' => '1190000.00'], $body['detail']);
        self::assertSame('draft', $this->getJson('/api/v1/sales-invoices/'.$draft['id'])['status']);
        self::assertSame([], $this->entries(), 'Nothing posted.');
    }

    public function testAnInvoiceWithoutLinesIsNotEmitted(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service(), ['lines' => [], 'payments' => []]));

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('lines', $body['violations'][0]['field']);
        self::assertSame('Agrega al menos una línea.', $body['violations'][0]['message']);
    }

    public function testAFutureDateIsRefusedAndABackdatedOneEmitted(): void
    {
        $client = $this->client();
        $product = $this->service();
        $future = $this->draft($this->payload($client, $product, ['issue_date' => self::today('+1 day')]));
        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$future['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422, '§9 Q11: not in the future.');
        self::assertSame('issue_date', $body['violations'][0]['field']);

        $past = $this->draft($this->payload($client, $product, ['issue_date' => self::today('-10 days')]));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$past['id'].'/emit', []);
        self::assertResponseIsSuccessful('Backdated within the open period.');
        self::assertSame(self::today('-10 days'), $this->entries()[0]->entryDate()->format('Y-m-d'), 'Posted on the invoice date.');
        self::assertSame('FE-1', $invoice['number'], 'The refused emission spent no number.');
    }

    public function testNothingIsEmittedOnOrBeforeTheLockDate(): void
    {
        // AC-9.
        $this->lockBooks(self::today('-5 days'));
        $draft = $this->draft($this->payload($this->client(), $this->service(), ['issue_date' => self::today('-5 days')]));

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error']);
        self::assertSame([], $this->entries());
        self::assertSame(1, $this->getJson('/api/v1/company/resolution')['resolution']['next_number'], 'The number was given back.');
    }

    public function testAnInactiveClientTakesNoInvoice(): void
    {
        $client = $this->client();
        $draft = $this->draft($this->payload($client, $this->service()));
        $this->sendJson('POST', "/api/v1/terceros/$client/deactivate", []);

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_inactive', $body['error']);
    }

    public function testWithoutAResolutionNothingIsEmitted(): void
    {
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.', resolution: false);
        $draft = $this->draft($this->payload($this->client(), $this->service()));

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('resolution_missing', $body['error']);
    }

    public function testEmitAndSendMailsThePdfToTheClient(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service()));

        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseIsSuccessful();
        self::assertSame('paid', $invoice['status'], 'All in cash: nothing is owed.');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'facturas@cliente.co');
        self::assertEmailHeaderSame($email, 'Subject', 'Factura de venta FE-1 de Acme S.A.S.');
        self::assertEmailAttachmentCount($email, 1);
    }

    public function testEmitAndSendNeedsTheClientsEmail(): void
    {
        $draft = $this->draft($this->payload($this->client(email: null), $this->service()));

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_has_no_email', $body['error']);
        self::assertSame('draft', $this->getJson('/api/v1/sales-invoices/'.$draft['id'])['status'], 'Nothing was emitted.');
        self::assertEmailCount(0);
    }

    public function testAnEmittedInvoiceIsSentAgain(): void
    {
        $invoice = $this->emitted();

        $this->client->request('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/send');

        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
    }

    public function testADraftIsNotSent(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service()));

        $this->client->request('POST', '/api/v1/sales-invoices/'.$draft['id'].'/send');

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_emitted', $this->body()['error']);
    }

    public function testTheAccountantEmits(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service()));
        $this->signInAs(Role::Accountant);

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('§8 as changed on 2026-10-04: the accountant emits commercial documents.');
    }

    public function testAnotherCompanysInvoiceIsNotFound(): void
    {
        $draft = $this->draft($this->payload($this->client(), $this->service()));
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');

        foreach (['emit', 'emit-and-send', 'send', 'duplicate'] as $action) {
            $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/'.$action, []);
            self::assertResponseStatusCodeSame(404, "AC-10: $action another company's invoice.");
        }
    }
}
