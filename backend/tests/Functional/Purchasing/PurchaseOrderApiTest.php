<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Application\Document\PurchaseOrderPdf;
use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Órdenes de compra over the API (observations PRD §4.8): drafts with a factura de compra's lines and no formas de
 * pago, emission with the OC series and no journal entry, the PDF and the e-mail, void, duplicate, the list, and
 * another company's order is a 404 everywhere.
 */
final class PurchaseOrderApiTest extends ApiTestCase
{
    use PurchaseOrderFixtures;
    use WithholdingTaxes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    private function nextOrderNumber(): int
    {
        return array_column($this->getJson('/api/v1/company/numbering')['items'], null, 'kind')['purchase_order']['next_number'];
    }

    public function testADraftHasLinesByProductAndByAccountAndTheSameArithmeticAsAPurchaseInvoice(): void
    {
        $supplier = $this->supplierWithEmail();
        $product = $this->product();
        $order = $this->orderDraft($supplier, ['lines' => [
            $this->orderLine(),
            $this->orderLine(['product_id' => $product, 'account_id' => null, 'description' => 'Resmas', 'quantity' => '10', 'unit_price' => '20000', 'discount' => '10', 'charge_tax_id' => null, 'withholdings' => self::withholdings($this->taxId('ReteFuente compras 2,5 %', 'withholding'))]),
        ]]);

        self::assertSame(['draft', null, 'Papelería Andina S.A.S.', self::today(10)], [$order['status'], $order['number'], $order['tercero_name'], $order['expected_date']]);
        self::assertSame('Entregar en bodega', $order['notes']);
        self::assertSame(['1200000.00', '20000.00', '1180000.00', '190000.00', '4500.00', '1365500.00'], [$order['gross_total'], $order['discount_total'], $order['subtotal'], $order['tax_total'], $order['withholding_total'], $order['net_total']]);
        self::assertSame([null, 'RES-01'], array_column($order['lines'], 'product_code'), 'The product code is snapshotted (item 5).');
        self::assertSame('RES-01 · Resma carta', $order['lines'][1]['product_label']);
        self::assertSame('513595', substr((string) $order['lines'][0]['account_label'], 0, 6));
        self::assertCount(1, $order['withholdings_by_tax']);
        self::assertArrayNotHasKey('payments', $order, 'An order has no formas de pago.');
        self::assertNull($order['converted_invoice_id']);
    }

    public function testALineWithSeveralRetencionesKeepsEachOne(): void
    {
        $order = $this->orderDraft(null, ['lines' => [$this->orderLine(['withholdings' => self::withholdings($this->taxId('ReteFuente servicios 4 %', 'withholding'), $this->reteIca())])]]);

        self::assertCount(2, $order['lines'][0]['withholdings']);
        self::assertCount(2, $order['withholdings_by_tax']);
        self::assertSame(['1000000.00', '190000.00'], [$order['subtotal'], $order['tax_total']], 'IVA on top of the subtotal.');
    }

    public function testEveryProblemIsReportedAtOnceByFieldPath(): void
    {
        $supplier = $this->supplierWithEmail();
        $body = $this->sendJson('POST', '/api/v1/purchase-orders', $this->orderPayload($supplier, [
            'expected_date' => self::today(-3),
            'lines' => [
                $this->orderLine(['product_id' => $this->product(), 'description' => 'both']),
                $this->orderLine(['account_id' => $this->account('110505')->toRfc4122()]),
            ],
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('expected_date', $fields);
        self::assertContains('lines[0].account_id', $fields, 'A product or an account, not both.');
        self::assertContains('lines[1].account_id', $fields, 'An account that purchases may not use.');
        self::assertSame(0, $this->getJson('/api/v1/purchase-orders')['total'], 'Nothing was saved.');

        $this->sendJson('POST', '/api/v1/purchase-orders', $this->orderPayload($supplier, ['lines' => [$this->orderLine(['quantity' => '0'])]]));
        self::assertResponseStatusCodeSame(422);
        $this->sendJson('POST', '/api/v1/purchase-orders', $this->orderPayload(Uuid::v7()->toRfc4122()));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['tercero_id'], array_column($this->body()['violations'], 'field'));
    }

    public function testADraftIsRewrittenAndDeletedAnEmittedOneIsNot(): void
    {
        $supplier = $this->supplierWithEmail();
        $draft = $this->orderDraft($supplier);

        $rewritten = $this->sendJson('PUT', '/api/v1/purchase-orders/'.$draft['id'], $this->orderPayload($supplier, ['notes' => 'Otra nota', 'expected_date' => null, 'lines' => [$this->orderLine(['quantity' => '1'])]]));
        self::assertResponseIsSuccessful();
        self::assertSame(['Otra nota', null, '595000.00'], [$rewritten['notes'], $rewritten['expected_date'], $rewritten['net_total']]);
        self::assertCount(1, $rewritten['lines']);

        $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit', []);
        foreach ([['PUT', $this->orderPayload($supplier)], ['DELETE', []]] as [$method, $payload]) {
            $body = $this->sendJson($method, '/api/v1/purchase-orders/'.$draft['id'], $payload);
            self::assertResponseStatusCodeSame(409, $method);
            self::assertSame('document_not_draft', $body['error']);
        }

        $other = $this->orderDraft($supplier);
        $this->client->request('DELETE', '/api/v1/purchase-orders/'.$other['id']);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/v1/purchase-orders/'.$other['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmissionTakesTheNextOcNumberAndPostsNothing(): void
    {
        $first = $this->emittedOrder();
        $second = $this->emittedOrder($this->supplierWithEmail('Otro S.A.S.', '890903938'));

        self::assertSame(['emitted', 'OC-1', 'OC-2'], [$first['status'], $first['number'], $second['number']]);
        self::assertNotNull($first['emitted_at']);
        self::assertSame(0, $this->journalRows(), 'An order never writes the books.');
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal')['total']);
        self::assertSame(3, $this->nextOrderNumber(), 'The OC series advanced.');
    }

    public function testEmissionNeedsALineAnAnActiveSupplierAndAnEmittableDraft(): void
    {
        $supplier = $this->supplierWithEmail();
        $empty = $this->orderDraft($supplier, ['lines' => []]);
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$empty['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['lines'], array_column($body['violations'], 'field'));

        $future = $this->orderDraft($supplier, ['issue_date' => self::today(5), 'expected_date' => null]);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$future['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);

        $draft = $this->orderDraft($supplier);
        $this->sendJson('POST', "/api/v1/terceros/$supplier/deactivate", []);
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('supplier_inactive', $body['error']);
        self::assertSame('draft', $this->getJson('/api/v1/purchase-orders/'.$draft['id'])['status'], 'A refusal changes nothing.');
        self::assertSame(1, $this->nextOrderNumber(), 'No number was spent.');

        $emitted = $this->emittedOrder();
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$emitted['id'].'/emit', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_draft', $body['error']);
    }

    public function testEmitAndSendQueuesThePdfToTheSupplier(): void
    {
        $draft = $this->orderDraft();

        $order = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseIsSuccessful();
        self::assertSame('OC-1', $order['number']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage() ?? self::fail('No e-mail.');
        self::assertEmailAddressContains($email, 'To', 'ventas@papeleria.co');
        self::assertEmailHeaderSame($email, 'Subject', 'Orden de compra OC-1 de Acme S.A.S.');
        self::assertEmailAttachmentCount($email, 1);
        self::assertEmailTextBodyContains($email, 'OC-1');
    }

    public function testASupplierWithoutEmailRefusesToSendAndTheEmissionIsRolledBack(): void
    {
        $supplier = $this->supplierWithEmail();
        $this->db()->update('tercero', ['email' => null], ['id' => Uuid::fromString($supplier)->toBinary()]);
        $draft = $this->orderDraft($supplier);

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit-and-send', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_has_no_email', $body['error']);
        self::assertSame('draft', $this->getJson('/api/v1/purchase-orders/'.$draft['id'])['status']);
        self::assertEmailCount(0);
    }

    public function testAnEmittedOrderIsSentAgainButADraftOrAVoidedOneIsNot(): void
    {
        $order = $this->emittedOrder();
        $this->client->request('POST', '/api/v1/purchase-orders/'.$order['id'].'/send');
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);

        $draft = $this->orderDraft();
        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/send', []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('purchase_order_not_open', $body['error']);

        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => 'x']);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/send', []);
        self::assertResponseStatusCodeSame(409);
    }

    public function testTheEmailQuotaApplies(): void
    {
        $order = $this->emittedOrder();
        for ($i = 0; $i < 60; ++$i) {
            $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/send', []);
            if (429 === $this->client->getResponse()->getStatusCode()) {
                self::assertSame('too_many_emails', $this->body()['error']);

                return;
            }
        }
        self::fail('The e-mail quota never answered 429.');
    }

    public function testVoidingKeepsTheNumberAndNeedsAReason(): void
    {
        $order = $this->emittedOrder();

        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => '  ']);
        self::assertResponseStatusCodeSame(422);

        $voided = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => 'El proveedor ya no vende']);
        self::assertResponseIsSuccessful();
        self::assertSame(['voided', 'OC-1', 'El proveedor ya no vende'], [$voided['status'], $voided['number'], $voided['void_reason']]);
        self::assertNotNull($voided['voided_at']);
        self::assertSame(0, $this->journalRows(), 'Nothing to reverse.');

        $body = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => 'otra vez']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('purchase_order_not_open', $body['error']);

        $draft = $this->orderDraft();
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/void', ['reason' => 'x']);
        self::assertResponseStatusCodeSame(409, 'A draft is deleted, not voided.');
    }

    public function testDuplicatingMakesADraftDatedTodayFromAnyState(): void
    {
        $order = $this->emittedOrder(null, ['issue_date' => self::today(-5), 'expected_date' => self::today(5), 'lines' => [$this->orderLine(['product_id' => $this->product(), 'account_id' => null])]]);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/void', ['reason' => 'x']);

        foreach ([$order['id'], $this->orderDraft()['id']] as $id) {
            $copy = $this->sendJson('POST', "/api/v1/purchase-orders/$id/duplicate", []);
            self::assertResponseStatusCodeSame(201);
            self::assertSame(['draft', null, self::today(), null], [$copy['status'], $copy['number'], $copy['issue_date'], $copy['converted_invoice_id']]);
        }
        $copy = $this->sendJson('POST', '/api/v1/purchase-orders/'.$order['id'].'/duplicate', []);
        self::assertSame(self::today(10), $copy['expected_date'], 'The expected delivery keeps its distance from the order date.');
        self::assertSame($order['net_total'], $copy['net_total']);
        self::assertSame(['RES-01'], array_column($copy['lines'], 'product_code'));
    }

    public function testTheListFiltersByTextStatusDatesAndSupplier(): void
    {
        $andina = $this->supplierWithEmail();
        $otro = $this->supplierWithEmail('Distribuidora Sur S.A.S.', '890903938');
        $emitted = $this->emittedOrder($andina);
        $draft = $this->orderDraft($otro, ['issue_date' => self::today(-30), 'expected_date' => null]);
        $voided = $this->emittedOrder($otro);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$voided['id'].'/void', ['reason' => 'x']);

        $ids = fn (string $query) => array_column($this->getJson('/api/v1/purchase-orders'.$query)['items'], 'id');
        self::assertSame(3, $this->getJson('/api/v1/purchase-orders')['total']);
        self::assertSame([$emitted['id']], $ids('?status=emitted'));
        self::assertSame([$draft['id']], $ids('?status=draft'));
        self::assertSame([$voided['id']], $ids('?status=voided'));
        self::assertSame([$emitted['id']], $ids('?q=OC-1'));
        self::assertEqualsCanonicalizing([$draft['id'], $voided['id']], $ids('?q=Sur'));
        self::assertSame([], $ids('?q=%25'), 'The text is matched literally.');
        self::assertSame([$draft['id']], $ids('?to='.self::today(-1)));
        self::assertEqualsCanonicalizing([$emitted['id'], $voided['id']], $ids('?from='.self::today()));
        self::assertEqualsCanonicalizing([$draft['id'], $voided['id']], $ids('?tercero_id='.$otro));
        $row = $this->getJson('/api/v1/purchase-orders?status=emitted')['items'][0];
        self::assertSame(['OC-1', 'Papelería Andina S.A.S.', '1190000.00'], [$row['number'], $row['tercero_name'], $row['net_total']]);

        $this->client->request('GET', '/api/v1/purchase-orders?from=ayer');
        self::assertResponseStatusCodeSame(400);
    }

    public function testThePdfIsRenderedAndMarksDraftAndVoided(): void
    {
        $product = $this->product();
        $draft = $this->orderDraft(null, ['notes' => '<b>Urgente</b>', 'lines' => [$this->orderLine(['product_id' => $product, 'account_id' => null])]]);
        $twig = static::getContainer()->get(Environment::class);
        $render = fn (string $id) => $twig->render(PurchaseOrderPdf::TEMPLATE, static::getContainer()->get(PurchaseOrderPdf::class)->context($this->company, Uuid::fromString($id)));

        $html = $render($draft['id']);
        self::assertStringContainsString('BORRADOR', $html);
        self::assertStringContainsString('<td>RES-01</td>', $html, 'The Código column.');
        self::assertMatchesRegularExpression('#<th>Código</th>\s*<th>Descripción</th>#', $html);
        self::assertStringContainsString('&lt;b&gt;Urgente&lt;/b&gt;', $html, 'Text is escaped, never interpreted.');
        self::assertStringContainsString('$ 1.190.000,00', $html);

        $this->client->request('GET', '/api/v1/purchase-orders/'.$draft['id'].'/pdf');
        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', (string) $this->client->getResponse()->getContent());

        $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit', []);
        $emitted = $render($draft['id']);
        self::assertStringNotContainsString('BORRADOR', $emitted);
        self::assertStringContainsString('No. OC-1', $emitted);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/void', ['reason' => 'x']);
        self::assertStringContainsString('ANULADA', $render($draft['id']));
    }

    public function testAProductOnAnOrderCannotBeDeleted(): void
    {
        $product = $this->product();
        $this->orderDraft(null, ['lines' => [$this->orderLine(['product_id' => $product, 'account_id' => null])]]);

        $this->client->request('DELETE', "/api/v1/products/$product");

        self::assertResponseStatusCodeSame(409, 'A product used by a purchase order is deactivated, not deleted.');
    }

    public function testTheAccountantAndBillingWriteAndAnotherCompanysOrderIsNotFound(): void
    {
        $order = $this->emittedOrder();
        $draft = $this->orderDraft();
        $this->signInAs('accountant');
        self::assertSame(2, $this->getJson('/api/v1/purchase-orders')['total']);
        $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/duplicate', []);
        self::assertResponseStatusCodeSame(201);

        $this->signOut();
        $this->startPurchasing('bea@otra.co', '900765432', 'Otra S.A.S.');
        $id = $order['id'];
        $this->getJson("/api/v1/purchase-orders/$id");
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->getJson('/api/v1/purchase-orders')['total'], 'Nor listed.');
        foreach ([['PUT', "/$id"], ['POST', "/$id/emit"], ['POST', "/$id/emit-and-send"], ['POST', "/$id/send"], ['POST', "/$id/void"], ['POST', "/$id/duplicate"], ['POST', "/$id/convert"], ['DELETE', "/$id"], ['PUT', "/{$draft['id']}"]] as [$method, $path]) {
            $this->sendJson($method, '/api/v1/purchase-orders'.$path, 'PUT' === $method ? $this->orderPayload($this->supplier->toRfc4122()) : ['reason' => 'x']);
            self::assertResponseStatusCodeSame(404, "$method $path");
        }
        foreach (["/$id/pdf", '/not-an-id'] as $path) {
            $this->client->request('GET', '/api/v1/purchase-orders'.$path);
            self::assertResponseStatusCodeSame(404, "GET $path");
        }
        // Nor can another company's supplier be put on an order.
        $this->sendJson('POST', '/api/v1/purchase-orders', $this->orderPayload($order['tercero_id']));
        self::assertResponseStatusCodeSame(422);
    }

    public function testWithoutSigningInNothingIsServed(): void
    {
        $this->signOut();
        $this->getJson('/api/v1/purchase-orders');
        self::assertResponseStatusCodeSame(401);
    }
}
