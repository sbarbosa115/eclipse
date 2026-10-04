<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Sales\Application\Document\CashReceiptPdf;
use App\Sales\Domain\Model\CashReceipt;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Recibos de caja through the API (§4.9, §4.12, §4.15): the client's open receivables, a receipt emitted when saved
 * (number, A.2 entry, invoices collected), its refusals, the list, void, PDF and e-mail, roles and tenancy.
 */
final class CashReceiptApiTest extends ApiTestCase
{
    use CashReceiptFixtures;

    private string $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
        $this->cliente = $this->client();
    }

    public function testTheOpenReceivablesOfAClientOldestFirst(): void
    {
        $later = $this->onCredit($this->cliente, due: self::today('+60 days'));
        $sooner = $this->onCredit($this->cliente, due: self::today('+15 days'));
        $paid = $this->emitted([$this->cash('1190000.00')], $this->cliente);
        $other = $this->onCredit($this->client('Otro Cliente S.A.S.', number: '890900608'));

        $body = $this->getJson('/api/v1/cash-receipts/open-receivables?tercero_id='.$this->cliente);

        self::assertResponseIsSuccessful();
        self::assertSame([$sooner['number'], $later['number']], array_column($body['items'], 'invoice_number'), 'Only this client’s open ones, the soonest due first; a cash invoice owes nothing.');
        self::assertNotContains($paid['number'], array_column($body['items'], 'invoice_number'));
        self::assertNotContains($other['number'], array_column($body['items'], 'invoice_number'));
        self::assertSame([
            'id' => $sooner['receivables'][0]['id'],
            'invoice_id' => $sooner['id'],
            'invoice_number' => $sooner['number'],
            'issue_date' => self::today(),
            'due_date' => self::today('+15 days'),
            'amount' => '1190000.00',
            'balance' => '1190000.00',
        ], $body['items'][0], 'Factura, fecha, vencimiento, valor, saldo (§4.9).');
    }

    public function testAReceiptIsEmittedPostedAndCollectsTheInvoices(): void
    {
        $first = $this->onCredit($this->cliente);
        $second = $this->onCredit($this->cliente);

        $receipt = $this->receive($this->cliente, '1500000.00', [[$first['receivables'][0]['id'], '1190000.00'], [$second['receivables'][0]['id'], '310000.00']], ['notes' => 'Transferencia de octubre', 'payment_method_id' => $this->methodId('Transferencia')]);

        self::assertSame('emitted', $receipt['status']);
        self::assertSame('RC-1', $receipt['number'], 'Numbered from the company’s RC series.');
        self::assertSame(['Cliente Uno S.A.S.', 'Transferencia', '1500000.00', 'Transferencia de octubre'], [$receipt['tercero_name'], $receipt['method_name'], $receipt['amount'], $receipt['notes']]);
        self::assertSame([[$first['number'], '1190000.00'], [$second['number'], '310000.00']], array_map(static fn (array $a) => [$a['invoice_number'], $a['amount']], $receipt['allocations']));
        self::assertNotNull($receipt['journal_entry_id']);

        self::assertSame('paid', $this->getJson('/api/v1/sales-invoices/'.$first['id'])['status']);
        $partly = $this->getJson('/api/v1/sales-invoices/'.$second['id']);
        self::assertSame(['partially_paid', '880000.00'], [$partly['status'], $partly['balance']]);

        self::assertSame([
            ['11100501', '1500000.00', '0.00'],
            ['13050501', '0.00', '1190000.00'],
            ['13050501', '0.00', '310000.00'],
        ], self::movements($this->lastEntry()), 'A.2: Dr the method’s account; Cr 1305 per allocation.');
        self::assertSame('cash_receipt', $this->lastEntry()->sourceType());
    }

    public function testTheClientsOwnReceivableAccountIsCredited(): void
    {
        $own = $this->accountId('13051001');
        $this->em()->getConnection()->update('tercero', ['receivable_account_id' => Uuid::fromString($own)->toBinary()], ['id' => Uuid::fromString($this->cliente)->toBinary()]);
        $invoice = $this->onCredit($this->cliente);

        $this->receive($this->cliente, '1190000.00', [[$invoice['receivables'][0]['id'], '1190000.00']]);

        self::assertSame(['13051001', '0.00', '1190000.00'], self::movements($this->lastEntry())[1], '§4.2: the tercero’s override applies to the receipt as to the invoice.');
    }

    public function testTheAllocationsMustMatchTheAmountAndTheBalances(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receivable = $invoice['receivables'][0]['id'];

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '1200000.00', [[$receivable, '1190000.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('allocations_do_not_match_amount', $body['error'], '§9 Q16: no over-payment.');
        self::assertSame(['allocated_total' => '1190000.00', 'amount' => '1200000.00'], $body['detail']);

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '1190000.01', [[$receivable, '1190000.01']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('allocation_exceeds_balance', $body['error']);

        self::assertSame([], $this->getJson('/api/v1/cash-receipts')['items'], 'Nothing was emitted…');
        $this->receive($this->cliente, '1190000.00', [[$receivable, '1190000.00']]);
        $after = $this->getJson('/api/v1/cash-receipts?status=emitted');
        self::assertSame(['RC-1'], array_column($after['items'], 'number'), '…and no number was spent by the refusals.');
    }

    public function testShapeRefusalsByField(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receivable = $invoice['receivables'][0]['id'];

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', ['tercero_id' => '', 'receipt_date' => 'ayer', 'payment_method_id' => '', 'amount' => '1.234,5', 'allocations' => [['receivable_id' => 'x', 'amount' => '']]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        $fields = array_column($body['violations'], 'field');
        foreach (['tercero_id', 'receipt_date', 'payment_method_id', 'amount', 'allocations[0].receivable_id', 'allocations[0].amount'] as $field) {
            self::assertContains($field, $fields, "$field is named.");
        }

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', [[$receivable, '100.00']], ['receipt_date' => self::today('+1 day')]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['receipt_date'], array_column($body['violations'], 'field'), 'Not in the future (Colombian calendar).');
        self::assertSame('La fecha del recibo no puede ser futura.', $body['violations'][0]['message']);
    }

    public function testTheMoneyGoesIntoAnActiveContadoMethod(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $allocation = [[$invoice['receivables'][0]['id'], '100.00']];

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', $allocation, ['payment_method_id' => $this->methodId('Crédito')]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'), '*Dónde ingresa el dinero* is a contado method.');

        $this->sendJson('POST', '/api/v1/payment-methods/'.$this->methodId('Tarjeta débito').'/deactivate', []);
        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', $allocation, ['payment_method_id' => $this->methodId('Tarjeta débito')]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'), 'An inactive method takes nothing new.');

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', $allocation, ['payment_method_id' => Uuid::v7()->toRfc4122()]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'));
    }

    public function testOnlyThisClientsReceivables(): void
    {
        $other = $this->client('Otro Cliente S.A.S.', number: '890900608');
        $invoice = $this->onCredit($other);

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['allocations.0.receivable_id'], array_column($body['violations'], 'field'));
    }

    public function testNothingIsReceivedOnOrBeforeTheLockDate(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $this->lockBooks(self::today('-1 day'));

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']], ['receipt_date' => self::today('-1 day')]));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'AC-9.');
        self::assertSame('emitted', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status'], 'Nothing was collected.');
    }

    public function testVoidingGivesTheMoneyBackAndReversesTheEntry(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receipt = $this->receive($this->cliente, '1190000.00', [[$invoice['receivables'][0]['id'], '1190000.00']]);

        $voided = $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Cheque devuelto']);

        self::assertResponseIsSuccessful();
        self::assertSame(['voided', 'RC-1', 'Cheque devuelto'], [$voided['status'], $voided['number'], $voided['void_reason']], '§4.12: the number is kept, the reason recorded.');
        self::assertNotNull($voided['reversal_entry_id']);
        self::assertNotNull($voided['voided_at']);
        $back = $this->getJson('/api/v1/sales-invoices/'.$invoice['id']);
        self::assertSame(['emitted', '1190000.00'], [$back['status'], $back['balance']], 'The invoice is owed again.');

        self::assertSame([
            ['11050501', '0.00', '1190000.00'],
            ['13050501', '1190000.00', '0.00'],
        ], self::movements($this->lastEntry()), 'The reversing entry is the mirror.');
        self::assertSame(self::today(), $this->lastEntry()->entryDate()->format('Y-m-d'), 'Dated the void date, today.');

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Ya sin recibos']);
        self::assertResponseIsSuccessful('A voided receipt no longer holds the invoice (§4.12).');
    }

    public function testAVoidNeedsAReasonAnOpenDayAndHappensOnce(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receipt = $this->receive($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]);

        $body = $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['reason'], array_column($body['violations'], 'field'));

        $this->lockBooks(self::today());
        $body = $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Error']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'The reversal would be dated today, which is locked.');
        $this->lockBooks(self::today('-1 day'));

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Error']);
        self::assertResponseIsSuccessful();
        $body = $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Error']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_voided', $body['error']);
        self::assertSame('1190000.00', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['balance'], 'Given back once only.');
    }

    public function testTheListSearchesFiltersAndPages(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receivable = $invoice['receivables'][0]['id'];
        $first = $this->receive($this->cliente, '100.00', [[$receivable, '100.00']], ['receipt_date' => self::today('-3 days')]);
        $second = $this->receive($this->cliente, '200.00', [[$receivable, '200.00']]);
        $other = $this->client('Otro Cliente S.A.S.', number: '890900608');
        $theirs = $this->onCredit($other);
        $third = $this->receive($other, '300.00', [[$theirs['receivables'][0]['id'], '300.00']]);
        $this->sendJson('POST', '/api/v1/cash-receipts/'.$second['id'].'/void', ['reason' => 'x']);

        $all = $this->getJson('/api/v1/cash-receipts');
        self::assertSame(['RC-3', 'RC-2', 'RC-1'], array_column($all['items'], 'number'), 'Newest first.');
        self::assertSame(3, $all['total']);
        self::assertSame([
            'id' => $third['id'], 'status' => 'emitted', 'number' => 'RC-3', 'receipt_date' => self::today(), 'tercero_id' => $other,
            'tercero_name' => 'Otro Cliente S.A.S.', 'method_name' => 'Efectivo', 'amount' => '300.00', 'invoice_numbers' => [$theirs['number']],
        ], $all['items'][0]);

        self::assertSame(['RC-3'], array_column($this->getJson('/api/v1/cash-receipts?q=otro')['items'], 'number'), 'By client name.');
        self::assertSame(['RC-1'], array_column($this->getJson('/api/v1/cash-receipts?q=RC-1')['items'], 'number'), 'By number.');
        self::assertSame([], $this->getJson('/api/v1/cash-receipts?q=%25')['items'], '% is literal.');
        self::assertSame(['RC-2'], array_column($this->getJson('/api/v1/cash-receipts?status=voided')['items'], 'number'));
        self::assertSame(['RC-1'], array_column($this->getJson('/api/v1/cash-receipts?to='.self::today('-1 day'))['items'], 'number'));
        self::assertSame(['RC-3', 'RC-2'], array_column($this->getJson('/api/v1/cash-receipts?from='.self::today())['items'], 'number'));
        self::assertSame(['RC-2', 'RC-1'], array_column($this->getJson('/api/v1/cash-receipts?tercero_id='.$this->cliente)['items'], 'number'));
        $page = $this->getJson('/api/v1/cash-receipts?per_page=2&page=2');
        self::assertSame([['RC-1'], 2, 2, 3], [array_column($page['items'], 'number'), $page['page'], $page['per_page'], $page['total']]);

        $this->getJson('/api/v1/cash-receipts?from=03/10/2026');
        self::assertResponseStatusCodeSame(400);
        self::assertSame($first['id'], $this->getJson('/api/v1/cash-receipts/'.$first['id'])['id']);
    }

    public function testTheListDoesNotGrowItsQueriesWithItsRows(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receivable = $invoice['receivables'][0]['id'];
        $count = function (): int {
            $this->client->enableProfiler();
            $this->getJson('/api/v1/cash-receipts');
            $profile = $this->client->getProfile();
            self::assertInstanceOf(\Symfony\Component\HttpKernel\Profiler\Profile::class, $profile, 'The profiler is on in the test environment.');
            $collector = $profile->getCollector('db');
            \assert($collector instanceof \Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector);

            return $collector->getQueryCount();
        };
        $this->receive($this->cliente, '100.00', [[$receivable, '100.00']]);
        $one = $count();
        foreach (['200.00', '300.00', '400.00'] as $amount) {
            $this->receive($this->cliente, $amount, [[$receivable, $amount]]);
        }

        self::assertSame($one, $count(), 'No N+1: the allocations of a page are read in one query.');
    }

    public function testCreateAndSendMailsThePdf(): void
    {
        $invoice = $this->onCredit($this->cliente);

        $receipt = $this->receive($this->cliente, '1190000.00', [[$invoice['receivables'][0]['id'], '1190000.00']], ['send' => true]);

        self::assertSame('emitted', $receipt['status']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'facturas@cliente.co');
        self::assertEmailHeaderSame($email, 'Subject', 'Recibo de caja RC-1 de Acme S.A.S.');
        self::assertEmailAttachmentCount($email, 1);

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/send', []);
        self::assertResponseStatusCodeSame(202, 'Sent again from the list.');
        self::assertEmailCount(1, message: 'Each request queues one e-mail.');
    }

    public function testCreateAndSendNeedsTheClientsEmail(): void
    {
        $silent = $this->client('Sin Correo S.A.S.', null, '890900608');
        $invoice = $this->onCredit($silent);

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($silent, '100.00', [[$invoice['receivables'][0]['id'], '100.00']], ['send' => true]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_has_no_email', $body['error']);
        self::assertSame([], $this->getJson('/api/v1/cash-receipts')['items'], 'Nothing was emitted.');
        self::assertEmailCount(0);
    }

    public function testThePdfShowsTheReceiptAndAnuladaOnceVoided(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receipt = $this->receive($this->cliente, '1190000.00', [[$invoice['receivables'][0]['id'], '1190000.00']], ['notes' => 'Pago total']);

        $html = $this->html($receipt['id']);
        foreach (['Acme S.A.S.', 'NIT 900123456', 'Recibo de caja', 'No. RC-1', 'Cliente Uno S.A.S.', 'NIT 800197268', 'Efectivo', $invoice['number'], '$ 1.190.000,00', 'Pago total'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('ANULADA', $html);

        $this->client->request('GET', '/api/v1/cash-receipts/'.$receipt['id'].'/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Consignación rechazada']);
        $html = $this->html($receipt['id']);
        self::assertStringContainsString('<div class="voided">ANULADA</div>', $html, '§4.12: the PDF shows ANULADA.');
        self::assertStringContainsString('Motivo: Consignación rechazada', $html);
    }

    public function testTheAccountantReceivesAndVoids(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $payload = $this->receiptPayload($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]);
        $this->signInAs(Role::Accountant);

        // §8 as changed on 2026-10-04: the accountant writes every document.
        $receipt = $this->sendJson('POST', '/api/v1/cash-receipts', $payload);
        self::assertResponseStatusCodeSame(201, 'The accountant receives money.');
        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Valor equivocado']);
        self::assertResponseIsSuccessful('and voids a receipt.');
    }

    public function testABillingUserReceives(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $this->signInAs(Role::Billing);

        $this->receive($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]);
    }

    public function testAnotherCompanysReceiptsAndReceivablesAreUnknown(): void
    {
        $invoice = $this->onCredit($this->cliente);
        $receipt = $this->receive($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]);
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');
        $mine = $this->client('Mi Cliente S.A.S.');

        self::assertSame([], $this->getJson('/api/v1/cash-receipts')['items'], 'AC-10: none of company A’s receipts.');
        foreach (['', '/pdf'] as $suffix) {
            $this->client->request('GET', '/api/v1/cash-receipts/'.$receipt['id'].$suffix);
            self::assertResponseStatusCodeSame(404, "GET $suffix");
        }
        foreach (['/void' => ['reason' => 'x'], '/send' => []] as $suffix => $payload) {
            $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].$suffix, $payload);
            self::assertResponseStatusCodeSame(404, "POST $suffix");
        }
        $this->getJson('/api/v1/cash-receipts/open-receivables?tercero_id='.$this->cliente);
        self::assertResponseStatusCodeSame(404, 'Company A’s client is unknown here.');

        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($mine, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['allocations.0.receivable_id'], array_column($body['violations'], 'field'), 'Company A’s receivable is refused as unknown.');
        $body = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($this->cliente, '100.00', [[$invoice['receivables'][0]['id'], '100.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertContains('tercero_id', array_column($body['violations'], 'field'), 'Company A’s client is unknown too.');

        $this->getJson('/api/v1/cash-receipts/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    private function html(string $receiptId): string
    {
        $this->em()->clear();
        $receipt = $this->em()->find(CashReceipt::class, Uuid::fromString($receiptId));
        \assert($receipt instanceof CashReceipt);

        return static::getContainer()->get(Environment::class)->render(CashReceiptPdf::TEMPLATE, static::getContainer()->get(CashReceiptPdf::class)->context($receipt));
    }
}
