<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Ledger\Domain\Model\JournalEntry;
use App\Sales\Application\Document\QuotationPdf;
use App\Sales\Domain\Model\Quotation;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Drafts (§4.6, §4.7), emission with series C and no entry, the answers (accept, reject, void), expiry read on the day
 * (§9 Q17), duplicate, the list (§4.15), the PDF and its e-mail, who may write (§8) and tenancy (AC-10).
 */
final class QuotationApiTest extends ApiTestCase
{
    use QuotationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testADraftHasTheLinesAndTotalsOfAnInvoiceAndItsOwnFields(): void
    {
        $client = $this->client();
        $product = $this->service();
        $employee = $this->employee();

        $quotation = $this->quotationDraft($this->quotationPayload($client, $product, [
            'responsible_id' => $employee,
            'header' => "Estimada Ana:\n\nEsta es nuestra propuesta.",
            'terms' => '50 % de anticipo.',
            'notes' => 'Incluye transporte',
            'lines' => [$this->line($product, ['quantity' => '2', 'discount' => '10', 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %')])],
        ]));

        self::assertSame('draft', $quotation['status']);
        self::assertNull($quotation['number']);
        self::assertSame('Cliente Uno S.A.S.', $quotation['tercero_name']);
        self::assertSame($employee, $quotation['responsible_id']);
        self::assertSame('Elena Vendedora Pérez', $quotation['responsible_name']);
        self::assertSame("Estimada Ana:\n\nEsta es nuestra propuesta.", $quotation['header']);
        self::assertSame('50 % de anticipo.', $quotation['terms']);
        self::assertSame(['2000000.00', '200000.00', '1800000.00', '342000.00', '72000.00', '2070000.00'], [$quotation['gross_total'], $quotation['discount_total'], $quotation['subtotal'], $quotation['tax_total'], $quotation['withholding_total'], $quotation['net_total']], 'The same arithmetic as an invoice (§4.6).');
        self::assertSame('CONS · Consultoría', $quotation['lines'][0]['product_label']);
        self::assertSame('IVA 19 %', $quotation['lines'][0]['charge_tax_name']);
        self::assertArrayNotHasKey('payments', $quotation, 'A quotation has no formas de pago.');
    }

    public function testTheOfferIsValidForThirtyDaysByDefault(): void
    {
        $quotation = $this->quotationDraft($this->quotationPayload($this->client(), $this->service(), ['issue_date' => self::today('-2 days')]));

        self::assertSame(self::today('+28 days'), $quotation['expiry_date'], '§9 Q17: 30 days from the quotation date.');

        $custom = $this->quotationDraft($this->quotationPayload($this->client(number: '890903938'), $this->service('B'), ['expiry_date' => self::today('+7 days')]));
        self::assertSame(self::today('+7 days'), $custom['expiry_date']);
    }

    public function testTheDraftIsRefusedByFieldPath(): void
    {
        $client = $this->client();
        $product = $this->service();
        $notEmployee = $client;

        $body = $this->sendJson('POST', '/api/v1/quotations', $this->quotationPayload($client, $product, [
            'responsible_id' => $notEmployee,
            'expiry_date' => self::today('-3 days'),
            'lines' => [$this->line($product, ['product_id' => Uuid::v7()->toRfc4122(), 'charge_tax_id' => Uuid::v7()->toRfc4122()])],
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('responsible_id', $fields, 'The responsable is a tercero with the role empleado.');
        self::assertContains('expiry_date', $fields, 'The offer cannot expire before it is made.');
        self::assertContains('lines.0.product_id', $fields);
        self::assertContains('lines.0.charge_tax_id', $fields);
        self::assertSame(0, $this->getJson('/api/v1/quotations')['total'], 'Nothing was saved.');

        $this->sendJson('POST', '/api/v1/quotations', $this->quotationPayload($client, $product, ['lines' => [$this->line($product, ['quantity' => '0'])]]));
        self::assertResponseStatusCodeSame(422, 'The shape of a line is checked before the catalogs.');
    }

    public function testTheTextsAreKeptAsTypedAndNeverInterpreted(): void
    {
        $quotation = $this->quotationDraft($this->quotationPayload($this->client(), $this->service(), ['header' => '<script>alert(1)</script> <b>Hola</b>']));

        self::assertSame('<script>alert(1)</script> <b>Hola</b>', $quotation['header'], 'JSON carries what was typed; the screens escape it.');
        $html = static::getContainer()->get('twig')->render(QuotationPdf::TEMPLATE, static::getContainer()->get(QuotationPdf::class)->context($this->em()->getRepository(Quotation::class)->find(Uuid::fromString($quotation['id'])) ?? self::fail('No quotation.')));
        self::assertStringNotContainsString('<script>', $html, 'No HTML from users in the PDF.');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &lt;b&gt;Hola&lt;/b&gt;', $html);
    }

    public function testParagraphsAreRenderedAsParagraphs(): void
    {
        self::assertSame(['Uno', "Dos\nlínea"], QuotationPdf::paragraphs("  Uno \r\n\r\n\r\nDos\nlínea\n"));
        self::assertSame([], QuotationPdf::paragraphs("  \n"));
    }

    public function testADraftIsRewrittenWhileItIsADraft(): void
    {
        $client = $this->client();
        $draft = $this->quotationDraft($this->quotationPayload($client, $this->service()));

        $changed = $this->sendJson('PUT', '/api/v1/quotations/'.$draft['id'], $this->quotationPayload($client, $this->service('OTRO'), ['terms' => 'Contado', 'lines' => [$this->line($this->service('TRES'), ['quantity' => '3'])]]));

        self::assertResponseIsSuccessful();
        self::assertSame('Contado', $changed['terms']);
        self::assertSame('3570000.00', $changed['net_total']);
        self::assertCount(1, $changed['lines']);
    }

    public function testEmissionNumbersFromSeriesCFreezesAndPostsNothing(): void
    {
        $first = $this->emittedQuotation();
        $second = $this->emittedQuotation($this->client('Cliente Dos', number: '890903938'));

        self::assertSame(['emitted', 'C-1', 'C', 1], [$first['status'], $first['number'], $first['prefix'], $first['sequence']]);
        self::assertSame('C-2', $second['number']);
        self::assertNotNull($first['emitted_at']);
        $this->em()->clear();
        self::assertSame([], $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company]), 'No journal entry (§4.7).');

        $this->sendJson('PUT', '/api/v1/quotations/'.$first['id'], $this->quotationPayload($this->client(number: '811111111'), $this->service('Z')));
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_draft', $this->body()['error']);
        $this->sendJson('POST', '/api/v1/quotations/'.$first['id'].'/emit', []);
        self::assertResponseStatusCodeSame(409, 'Emitted once.');
    }

    public function testNoJournalPosterIsInvolvedInEmitting(): void
    {
        $handler = new \ReflectionClass(\App\Sales\Application\Command\EmitQuotationHandler::class);
        foreach ($handler->getConstructor()?->getParameters() ?? [] as $parameter) {
            self::assertNotSame(\App\Ledger\Application\Posting\JournalPoster::class, (string) $parameter->getType(), 'Emitting a quotation has no accounting effect: it does not even know the poster.');
        }
    }

    public function testEmissionNeedsALineAndADateThatIsNotInTheFuture(): void
    {
        $client = $this->client();
        $product = $this->service();
        $empty = $this->quotationDraft($this->quotationPayload($client, $product, ['lines' => []]));
        $body = $this->sendJson('POST', '/api/v1/quotations/'.$empty['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('lines', $body['violations'][0]['field']);

        $future = $this->quotationDraft($this->quotationPayload($client, $product, ['issue_date' => self::today('+1 day')]));
        $body = $this->sendJson('POST', '/api/v1/quotations/'.$future['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('issue_date', $body['violations'][0]['field']);

        $ok = $this->quotationDraft($this->quotationPayload($client, $product));
        self::assertSame('C-1', $this->sendJson('POST', '/api/v1/quotations/'.$ok['id'].'/emit', [])['number'], 'The refusals spent no number.');
    }

    public function testEmitAndSendMailsThePdfToTheClient(): void
    {
        $draft = $this->quotationDraft($this->quotationPayload($this->client(), $this->service()));

        $quotation = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseIsSuccessful();
        self::assertSame('C-1', $quotation['number']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'facturas@cliente.co');
        self::assertEmailHeaderSame($email, 'Subject', 'Cotización C-1 de Acme S.A.S.');
        self::assertEmailAttachmentCount($email, 1);
    }

    public function testEmitAndSendNeedsTheClientsEmailAndEmitsNothingWithout(): void
    {
        $draft = $this->quotationDraft($this->quotationPayload($this->client(email: null), $this->service()));

        $body = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_has_no_email', $body['error']);
        self::assertSame('draft', $this->getJson('/api/v1/quotations/'.$draft['id'])['status']);
        self::assertEmailCount(0);
    }

    public function testAnEmittedQuotationIsSentAgainButADraftIsNot(): void
    {
        $quotation = $this->emittedQuotation();
        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/send', []);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);

        $draft = $this->quotationDraft($this->quotationPayload($this->client(number: '890903938'), $this->service('B')));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/send', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('quotation_not_open', $this->body()['error']);
    }

    public function testAcceptAndReject(): void
    {
        $accepted = $this->emittedQuotation();
        self::assertSame('accepted', $this->sendJson('POST', '/api/v1/quotations/'.$accepted['id'].'/accept', [])['status']);
        $this->sendJson('POST', '/api/v1/quotations/'.$accepted['id'].'/reject', []);
        self::assertResponseStatusCodeSame(409, 'Decided once.');
        self::assertSame('quotation_not_open', $this->body()['error']);

        $rejected = $this->emittedQuotation($this->client('Cliente Dos', number: '890903938'));
        self::assertSame('rejected', $this->sendJson('POST', '/api/v1/quotations/'.$rejected['id'].'/reject', [])['status']);
        $this->sendJson('POST', '/api/v1/quotations/'.$rejected['id'].'/accept', []);
        self::assertResponseStatusCodeSame(409);

        $draft = $this->quotationDraft($this->quotationPayload($this->client(number: '811111111'), $this->service('C')));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/accept', []);
        self::assertResponseStatusCodeSame(409, 'A draft is not decided.');
    }

    public function testAnEmittedQuotationPastItsVencimientoReadsAsExpired(): void
    {
        $quotation = $this->emittedQuotation();
        self::assertSame('emitted', $quotation['status']);

        $this->expireOn($quotation['id'], self::today('-1 day'));

        self::assertSame('expired', $this->getJson('/api/v1/quotations/'.$quotation['id'])['status'], 'Computed on read: no job to run.');
        self::assertSame(['expired'], array_column($this->getJson('/api/v1/quotations')['items'], 'status'));
        self::assertSame(1, $this->getJson('/api/v1/quotations?status=expired')['total']);
        self::assertSame(0, $this->getJson('/api/v1/quotations?status=emitted')['total']);
        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/accept', []);
        self::assertResponseStatusCodeSame(409, 'An expired offer is not accepted.');
        self::assertSame('quotation_not_open', $this->body()['error']);

        $this->expireOn($quotation['id'], self::today());
        self::assertSame('emitted', $this->getJson('/api/v1/quotations/'.$quotation['id'])['status'], 'It is valid through its vencimiento day.');
    }

    public function testAnEmittedQuotationIsVoidedWithAReason(): void
    {
        $quotation = $this->emittedQuotation();

        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/void', ['reason' => '']);
        self::assertResponseStatusCodeSame(422);

        $voided = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/void', ['reason' => 'El cliente cambió el alcance']);

        self::assertResponseIsSuccessful();
        self::assertSame(['voided', 'C-1', 'El cliente cambió el alcance'], [$voided['status'], $voided['number'], $voided['void_reason']]);
        self::assertNotNull($voided['voided_at']);
        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/void', ['reason' => 'otra vez']);
        self::assertResponseStatusCodeSame(409);
        $this->em()->clear();
        self::assertSame([], $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company]), 'Nothing to reverse.');
    }

    public function testDuplicateMakesANewDraftDatedToday(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->quotationDraft($this->quotationPayload($client, $product, ['issue_date' => self::today('-10 days'), 'expiry_date' => self::today('+5 days'), 'header' => 'Hola', 'terms' => 'Contado', 'lines' => [$this->line($product, ['quantity' => '2'])]]));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);

        $copy = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/duplicate', []);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['draft', null, self::today(), self::today('+15 days'), 'Hola', 'Contado', '2380000.00'], [$copy['status'], $copy['number'], $copy['issue_date'], $copy['expiry_date'], $copy['header'], $copy['terms'], $copy['net_total']], 'The offer keeps its 15 days.');
        self::assertNotSame($draft['id'], $copy['id']);
    }

    public function testTheListSearchesFiltersAndPages(): void
    {
        $a = $this->emittedQuotation($this->client('Alfa S.A.S.', number: '800197268'));
        $b = $this->emittedQuotation($this->client('Beta S.A.S.', number: '890903938'));
        $this->sendJson('POST', '/api/v1/quotations/'.$b['id'].'/reject', []);
        $this->quotationDraft($this->quotationPayload($this->client('Gamma S.A.S.', number: '811111111'), $this->service('G'), ['issue_date' => self::today('-40 days')]));

        $all = $this->getJson('/api/v1/quotations');
        self::assertSame(3, $all['total']);
        self::assertSame(['Beta S.A.S.', 'Alfa S.A.S.'], \array_slice(array_column($all['items'], 'tercero_name'), 0, 2), 'Newest first: C-2 before C-1.');
        self::assertSame([$a['id']], array_column($this->getJson('/api/v1/quotations?q=alfa')['items'], 'id'));
        self::assertSame([$a['id']], array_column($this->getJson('/api/v1/quotations?q=C-1')['items'], 'id'));
        self::assertSame([$b['id']], array_column($this->getJson('/api/v1/quotations?status=rejected')['items'], 'id'));
        self::assertSame(1, $this->getJson('/api/v1/quotations?status=draft')['total']);
        self::assertSame(2, $this->getJson('/api/v1/quotations?from='.self::today('-1 day'))['total']);
        self::assertSame(1, $this->getJson('/api/v1/quotations?to='.self::today('-30 days'))['total']);
        self::assertSame(0, $this->getJson('/api/v1/quotations?q=%25')['total'], 'A search is literal.');
        $paged = $this->getJson('/api/v1/quotations?per_page=2&page=2');
        self::assertSame([1, 2, 3], [\count($paged['items']), $paged['page'], $paged['total']]);
        $this->getJson('/api/v1/quotations?from=hoy');
        self::assertResponseStatusCodeSame(400);
        self::assertSame(['id', 'status', 'number', 'issue_date', 'expiry_date', 'tercero_id', 'tercero_name', 'subtotal', 'tax_total', 'withholding_total', 'net_total', 'converted_invoice_id'], array_keys($all['items'][0]));
    }

    public function testThePdfIsDownloadedAndMarkedWhenVoided(): void
    {
        $quotation = $this->emittedQuotation();

        $this->client->request('GET', '/api/v1/quotations/'.$quotation['id'].'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('cotizacion-C-1.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());

        $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/void', ['reason' => 'x']);
        $document = static::getContainer()->get(QuotationPdf::class)->context($this->em()->getRepository(Quotation::class)->find(Uuid::fromString($quotation['id'])) ?? self::fail('No quotation.'));
        self::assertSame('ANULADA', $document['banner']);
    }

    public function testTheAccountantWritesQuotations(): void
    {
        $client = $this->client();
        $product = $this->service();
        $this->signInAs(Role::Accountant);

        // §8 as changed on 2026-10-04: the accountant writes every document.
        $draft = $this->sendJson('POST', '/api/v1/quotations', $this->quotationPayload($client, $product));
        self::assertResponseStatusCodeSame(201);
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('The accountant emits a quotation.');
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/convert', []);
        self::assertResponseIsSuccessful('and converts it.');
    }

    public function testABillingUserWrites(): void
    {
        $client = $this->client();
        $product = $this->service();
        $this->signInAs(Role::Billing);

        $draft = $this->quotationDraft($this->quotationPayload($client, $product));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);

        self::assertResponseIsSuccessful();
    }

    public function testAnotherCompanySeesNothing(): void
    {
        $client = $this->client();
        $product = $this->service();
        $draft = $this->quotationDraft($this->quotationPayload($client, $product));
        $emitted = $this->emittedQuotation($client, $this->service('E'));
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');

        self::assertSame(0, $this->getJson('/api/v1/quotations')['total'], 'AC-10: no rows of company A.');
        $mine = $this->quotationPayload($this->client('Mío', number: '811111111'), $this->service('MIO'));
        foreach ([
            ['GET', $draft['id'], ''], ['GET', $draft['id'], '/pdf'], ['PUT', $draft['id'], ''],
            ['POST', $draft['id'], '/emit'], ['POST', $draft['id'], '/emit-and-send'], ['POST', $emitted['id'], '/send'],
            ['POST', $emitted['id'], '/accept'], ['POST', $emitted['id'], '/reject'], ['POST', $emitted['id'], '/void'],
            ['POST', $emitted['id'], '/convert'], ['POST', $emitted['id'], '/duplicate'],
        ] as [$method, $id, $suffix]) {
            $this->sendJson($method, '/api/v1/quotations/'.$id.$suffix, 'PUT' === $method ? $mine : ['reason' => 'x']);
            self::assertResponseStatusCodeSame(404, "AC-10: $method $suffix of another company's quotation.");
        }
        $this->getJson('/api/v1/quotations/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSignedOutIsRefused(): void
    {
        $this->signOut();

        $this->getJson('/api/v1/quotations');

        self::assertResponseStatusCodeSame(401);
    }
}
