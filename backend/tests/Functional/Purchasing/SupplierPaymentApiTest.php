<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Application\Document\SupplierPaymentPdf;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Recibos de pago through the API (§4.11, §4.12, §4.15): the supplier's open payables, a payment emitted when saved
 * (number, A.4 entry, invoices paid), its refusals, the list, void, PDF and e-mail, roles and tenancy.
 */
final class SupplierPaymentApiTest extends ApiTestCase
{
    use SupplierPaymentFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
        $this->supplier = $this->supplierNamed('Servicios Andinos S.A.S.');
    }

    /** @param array<mixed> $invoice */
    private static function payableOf(array $invoice): string
    {
        return $invoice['payables'][0]['id'];
    }

    public function testTheOpenPayablesOfASupplierOldestFirst(): void
    {
        $later = $this->owed(number: 'A-1', over: ['due_date' => self::today(60), 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1150000.00', 'due_date' => self::today(60)]]]);
        $sooner = $this->owed(number: 'A-2', over: ['due_date' => self::today(15), 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1150000.00', 'due_date' => self::today(15)]]]);
        $other = $this->owed($this->supplierNamed('Otro Proveedor S.A.S.'), 'B-1');

        $body = $this->getJson('/api/v1/supplier-payments/open-payables?tercero_id='.$this->supplier->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSame([$sooner['number'], $later['number']], array_column($body['items'], 'invoice_number'), 'Only this supplier’s open ones, the soonest due first.');
        self::assertNotContains($other['number'], array_column($body['items'], 'invoice_number'));
        self::assertSame([
            'id' => self::payableOf($sooner),
            'invoice_id' => $sooner['id'],
            'invoice_number' => $sooner['number'],
            'issue_date' => self::today(-1),
            'due_date' => self::today(15),
            'amount' => '1150000.00',
            'balance' => '1150000.00',
        ], $body['items'][0], 'Factura, fecha, vencimiento, valor, saldo (§4.11).');
    }

    public function testAPaymentIsEmittedPostedAndPaysTheInvoices(): void
    {
        $first = $this->owed(number: 'A-1');
        $second = $this->owed(number: 'A-2');

        $payment = $this->pay($this->supplier, '1500000.00', [[self::payableOf($first), '1150000.00'], [self::payableOf($second), '350000.00']], ['notes' => 'Transferencia de octubre', 'payment_method_id' => $this->methodId('Transferencia')]);

        self::assertSame('emitted', $payment['status']);
        self::assertSame('RP-1', $payment['number'], 'Numbered from the company’s RP series.');
        self::assertSame(['Servicios Andinos S.A.S.', 'Transferencia', '1500000.00', 'Transferencia de octubre'], [$payment['tercero_name'], $payment['method_name'], $payment['amount'], $payment['notes']]);
        self::assertSame([[$first['number'], '1150000.00'], [$second['number'], '350000.00']], array_map(static fn (array $a) => [$a['invoice_number'], $a['amount']], $payment['allocations']));
        self::assertNotNull($payment['journal_entry_id']);

        self::assertSame('paid', $this->purchase($first['id'])['status']);
        $partly = $this->purchase($second['id']);
        self::assertSame(['partially_paid', '800000.00', '800000.00'], [$partly['status'], $partly['balance'], $partly['payables'][0]['balance']]);

        $supplier = $this->supplier->toRfc4122();
        self::assertSame([
            ['22050501', '1150000.00', '0.00', $supplier],
            ['22050501', '350000.00', '0.00', $supplier],
            ['11100501', '0.00', '1500000.00', $supplier],
        ], self::movements($this->lastEntry()), 'A.4: Dr 2205 per allocation; Cr the method’s account.');
        self::assertSame('supplier_payment', $this->lastEntry()->sourceType());
    }

    public function testTheSuppliersOwnPayableAccountIsDebited(): void
    {
        $own = $this->account('233525');
        $this->db()->update('tercero', ['payable_account_id' => $own->toBinary()], ['id' => $this->supplier->toBinary()]);
        $invoice = $this->owed();

        $this->pay($this->supplier, '1150000.00', [[self::payableOf($invoice), '1150000.00']]);

        self::assertSame(['233525', '1150000.00', '0.00'], \array_slice(self::movements($this->lastEntry())[0], 0, 3), '§4.2: the tercero’s override applies to the payment as to the invoice.');
    }

    public function testTheAllocationsMustMatchTheAmountAndTheBalances(): void
    {
        $invoice = $this->owed();
        $payable = self::payableOf($invoice);

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '1200000.00', [[$payable, '1150000.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('allocations_do_not_match_amount', $body['error'], '§9 Q16: no over-payment.');
        self::assertSame(['allocated_total' => '1150000.00', 'amount' => '1200000.00'], $body['detail']);

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '1150000.01', [[$payable, '1150000.01']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('allocation_exceeds_balance', $body['error']);

        self::assertSame([], $this->getJson('/api/v1/supplier-payments')['items'], 'Nothing was emitted…');
        $this->pay($this->supplier, '1150000.00', [[$payable, '1150000.00']]);
        self::assertSame(['RP-1'], array_column($this->getJson('/api/v1/supplier-payments?status=emitted')['items'], 'number'), '…and no number was spent by the refusals.');
    }

    public function testShapeRefusalsByField(): void
    {
        $invoice = $this->owed();

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', ['tercero_id' => '', 'receipt_date' => 'ayer', 'payment_method_id' => '', 'amount' => '1.234,5', 'allocations' => [['payable_id' => 'x', 'amount' => '']]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        $fields = array_column($body['violations'], 'field');
        foreach (['tercero_id', 'receipt_date', 'payment_method_id', 'amount', 'allocations[0].payable_id', 'allocations[0].amount'] as $field) {
            self::assertContains($field, $fields, "$field is named.");
        }

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']], ['receipt_date' => self::today(1)]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['receipt_date'], array_column($body['violations'], 'field'), 'Not in the future (Colombian calendar).');
        self::assertSame('La fecha del pago no puede ser futura.', $body['violations'][0]['message']);
    }

    public function testTheMoneyGoesOutOfAnActiveContadoMethod(): void
    {
        $allocation = [[self::payableOf($this->owed()), '100.00']];

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', $allocation, ['payment_method_id' => $this->methodId('Crédito')]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'), '*De dónde sale el dinero* is a contado method.');

        $this->sendJson('POST', '/api/v1/payment-methods/'.$this->methodId('Tarjeta débito').'/deactivate', []);
        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', $allocation, ['payment_method_id' => $this->methodId('Tarjeta débito')]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'), 'An inactive method takes nothing new.');

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', $allocation, ['payment_method_id' => Uuid::v7()->toRfc4122()]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['payment_method_id'], array_column($body['violations'], 'field'));
    }

    public function testOnlyThisSuppliersPayables(): void
    {
        $invoice = $this->owed($this->supplierNamed('Otro Proveedor S.A.S.'), 'B-1');

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['allocations.0.payable_id'], array_column($body['violations'], 'field'));
    }

    public function testAVoidedInvoiceIsNotPaid(): void
    {
        $invoice = $this->owed();
        $this->sendJson('POST', '/api/v1/purchase-invoices/'.$invoice['id'].'/void', ['reason' => 'Duplicada']);
        self::assertResponseIsSuccessful();

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['allocations.0.payable_id'], array_column($body['violations'], 'field'));
    }

    public function testNothingIsPaidOnOrBeforeTheLockDate(): void
    {
        $invoice = $this->owed();
        $this->lockBooks(self::today(-1));

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']], ['receipt_date' => self::today(-1)]));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'AC-9.');
        self::assertSame('emitted', $this->purchase($invoice['id'])['status'], 'Nothing was paid.');
        self::assertSame([], $this->getJson('/api/v1/supplier-payments')['items']);
    }

    public function testVoidingGivesTheMoneyBackAndReversesTheEntry(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '1150000.00', [[self::payableOf($invoice), '1150000.00']]);
        self::assertSame('paid', $this->purchase($invoice['id'])['status']);

        $voided = $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Transferencia rechazada']);

        self::assertResponseIsSuccessful();
        self::assertSame(['voided', 'RP-1', 'Transferencia rechazada'], [$voided['status'], $voided['number'], $voided['void_reason']], '§4.12: the number is kept, the reason recorded.');
        self::assertNotNull($voided['reversal_entry_id']);
        self::assertNotNull($voided['voided_at']);
        $back = $this->purchase($invoice['id']);
        self::assertSame(['emitted', '1150000.00'], [$back['status'], $back['balance']], 'The invoice is owed again.');
        self::assertSame('1150000.00', $back['payables'][0]['balance']);

        $supplier = $this->supplier->toRfc4122();
        self::assertSame([
            ['22050501', '0.00', '1150000.00', $supplier],
            ['11050501', '1150000.00', '0.00', $supplier],
        ], self::movements($this->lastEntry()), 'The reversing entry is the mirror.');
        self::assertSame(self::today(), $this->lastEntry()->entryDate()->format('Y-m-d'), 'Dated the void date, today.');

        $this->sendJson('POST', '/api/v1/purchase-invoices/'.$invoice['id'].'/void', ['reason' => 'Ya sin pagos']);
        self::assertResponseIsSuccessful('A voided payment no longer holds the invoice (§4.12).');
    }

    public function testAVoidNeedsAReasonAnOpenDayAndHappensOnce(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]);

        $body = $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['reason'], array_column($body['violations'], 'field'));

        $this->lockBooks(self::today());
        $body = $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Error']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'The reversal would be dated today, which is locked.');
        self::assertSame('1149900.00', $this->purchase($invoice['id'])['balance'], 'A refused void gives nothing back.');
        $this->lockBooks(self::today(-1));

        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Error']);
        self::assertResponseIsSuccessful();
        $body = $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Error']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_voided', $body['error']);
        self::assertSame('1150000.00', $this->purchase($invoice['id'])['balance'], 'Given back once only.');
    }

    public function testTheInvoiceCannotBeVoidedWhileAPaymentHoldsItThenCan(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]);

        $body = $this->sendJson('POST', '/api/v1/purchase-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_has_allocations', $body['error']);

        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'x']);
        $this->sendJson('POST', '/api/v1/purchase-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);
        self::assertResponseIsSuccessful();
    }

    public function testTheListSearchesFiltersAndPages(): void
    {
        $invoice = $this->owed();
        $payable = self::payableOf($invoice);
        $first = $this->pay($this->supplier, '100.00', [[$payable, '100.00']], ['receipt_date' => self::today(-3)]);
        $second = $this->pay($this->supplier, '200.00', [[$payable, '200.00']]);
        $other = $this->supplierNamed('Otro Proveedor S.A.S.');
        $theirs = $this->owed($other, 'B-1');
        $third = $this->pay($other, '300.00', [[self::payableOf($theirs), '300.00']]);
        $this->sendJson('POST', '/api/v1/supplier-payments/'.$second['id'].'/void', ['reason' => 'x']);

        $all = $this->getJson('/api/v1/supplier-payments');
        self::assertSame(['RP-3', 'RP-2', 'RP-1'], array_column($all['items'], 'number'), 'Newest first.');
        self::assertSame(3, $all['total']);
        self::assertSame([
            'id' => $third['id'], 'status' => 'emitted', 'number' => 'RP-3', 'receipt_date' => self::today(), 'tercero_id' => $other->toRfc4122(),
            'tercero_name' => 'Otro Proveedor S.A.S.', 'method_name' => 'Efectivo', 'amount' => '300.00', 'invoice_numbers' => [$theirs['number']],
        ], $all['items'][0]);

        self::assertSame(['RP-3'], array_column($this->getJson('/api/v1/supplier-payments?q=otro')['items'], 'number'), 'By supplier name.');
        self::assertSame(['RP-1'], array_column($this->getJson('/api/v1/supplier-payments?q=RP-1')['items'], 'number'), 'By number.');
        self::assertSame([], $this->getJson('/api/v1/supplier-payments?q=%25')['items'], '% is literal.');
        self::assertSame(['RP-2'], array_column($this->getJson('/api/v1/supplier-payments?status=voided')['items'], 'number'));
        self::assertSame(['RP-1'], array_column($this->getJson('/api/v1/supplier-payments?to='.self::today(-1))['items'], 'number'));
        self::assertSame(['RP-3', 'RP-2'], array_column($this->getJson('/api/v1/supplier-payments?from='.self::today())['items'], 'number'));
        self::assertSame(['RP-2', 'RP-1'], array_column($this->getJson('/api/v1/supplier-payments?tercero_id='.$this->supplier->toRfc4122())['items'], 'number'));
        $page = $this->getJson('/api/v1/supplier-payments?per_page=2&page=2');
        self::assertSame([['RP-1'], 2, 2, 3], [array_column($page['items'], 'number'), $page['page'], $page['per_page'], $page['total']]);

        $this->getJson('/api/v1/supplier-payments?from=03/10/2026');
        self::assertResponseStatusCodeSame(400);
        self::assertSame($first['id'], $this->getJson('/api/v1/supplier-payments/'.$first['id'])['id']);
    }

    public function testTheListDoesNotGrowItsQueriesWithItsRows(): void
    {
        $payable = self::payableOf($this->owed());
        $count = function (): int {
            $this->client->enableProfiler();
            $this->getJson('/api/v1/supplier-payments');
            $profile = $this->client->getProfile();
            self::assertInstanceOf(\Symfony\Component\HttpKernel\Profiler\Profile::class, $profile, 'The profiler is on in the test environment.');
            $collector = $profile->getCollector('db');
            \assert($collector instanceof \Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector);

            return $collector->getQueryCount();
        };
        $this->pay($this->supplier, '100.00', [[$payable, '100.00']]);
        $one = $count();
        foreach (['200.00', '300.00', '400.00'] as $amount) {
            $this->pay($this->supplier, $amount, [[$payable, $amount]]);
        }

        self::assertSame($one, $count(), 'No N+1: the allocations of a page are read in one query.');
    }

    public function testCreateAndSendMailsThePdf(): void
    {
        $invoice = $this->owed();

        $payment = $this->pay($this->supplier, '1150000.00', [[self::payableOf($invoice), '1150000.00']], ['send' => true]);

        self::assertSame('emitted', $payment['status']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'pagos@proveedor.co');
        self::assertEmailHeaderSame($email, 'Subject', 'Recibo de pago RP-1 de Acme S.A.S.');
        self::assertEmailAttachmentCount($email, 1);

        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/send', []);
        self::assertResponseStatusCodeSame(202, 'Sent again from the list.');
        self::assertEmailCount(1, message: 'Each request queues one e-mail.');
    }

    public function testCreateAndSendNeedsTheSuppliersEmail(): void
    {
        $silent = $this->supplierNamed('Sin Correo S.A.S.', null);
        $invoice = $this->owed($silent, 'S-1');

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($silent, '100.00', [[self::payableOf($invoice), '100.00']], ['send' => true]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tercero_has_no_email', $body['error']);
        self::assertSame([], $this->getJson('/api/v1/supplier-payments')['items'], 'Nothing was emitted.');
        self::assertEmailCount(0);
    }

    public function testAVoidedPaymentIsNotSent(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]);
        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'x']);

        $body = $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/send', []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_emitted', $body['error']);
    }

    public function testThePdfShowsThePaymentAndAnuladaOnceVoided(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '1150000.00', [[self::payableOf($invoice), '1150000.00']], ['notes' => 'Pago total']);

        $html = $this->html($payment['id']);
        foreach (['Acme S.A.S.', 'NIT 900123456', 'Recibo de pago', 'No. RP-1', 'Servicios Andinos S.A.S.', 'Efectivo', $invoice['number'], '$ 1.150.000,00', 'Pago total'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('ANULADA', $html);

        $this->client->request('GET', '/api/v1/supplier-payments/'.$payment['id'].'/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');

        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Consignación rechazada']);
        $html = $this->html($payment['id']);
        self::assertStringContainsString('<div class="voided">ANULADA</div>', $html, '§4.12: the PDF shows ANULADA.');
        self::assertStringContainsString('Motivo: Consignación rechazada', $html);
    }

    public function testTheAccountantReadsPaysAndVoids(): void
    {
        $invoice = $this->owed();
        $payable = self::payableOf($invoice);
        $this->signInAs('accountant');

        $this->getJson('/api/v1/supplier-payments');
        self::assertResponseIsSuccessful();
        $this->getJson('/api/v1/supplier-payments/open-payables?tercero_id='.$this->supplier->toRfc4122());
        self::assertResponseIsSuccessful();

        $payment = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[$payable, '100.00']]));
        self::assertResponseStatusCodeSame(201, 'The accountant may pay.');
        $this->client->request('GET', '/api/v1/supplier-payments/'.$payment['id'].'/pdf');
        self::assertResponseIsSuccessful();
        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'x']);
        self::assertResponseIsSuccessful('The accountant may void.');
    }

    public function testASignedOutRequestIsRefused(): void
    {
        $this->signOut();

        $this->getJson('/api/v1/supplier-payments');
        self::assertResponseStatusCodeSame(401);
        $this->sendJson('POST', '/api/v1/supplier-payments', []);
        self::assertResponseStatusCodeSame(401);
    }

    public function testABillingUserPays(): void
    {
        $invoice = $this->owed();
        $this->signInAs('billing');

        $this->pay($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]);
    }

    public function testAnotherCompanysPaymentsAndPayablesAreUnknown(): void
    {
        $invoice = $this->owed();
        $payment = $this->pay($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]);
        $this->signOut();
        $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');
        $mine = $this->tercero('Mi Proveedor S.A.S.');

        self::assertSame([], $this->getJson('/api/v1/supplier-payments')['items'], 'AC-10: none of company A’s payments.');
        foreach (['', '/pdf'] as $suffix) {
            $this->client->request('GET', '/api/v1/supplier-payments/'.$payment['id'].$suffix);
            self::assertResponseStatusCodeSame(404, "GET $suffix");
        }
        foreach (['/void' => ['reason' => 'x'], '/send' => []] as $suffix => $payload) {
            $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].$suffix, $payload);
            self::assertResponseStatusCodeSame(404, "POST $suffix");
        }
        $this->getJson('/api/v1/supplier-payments/open-payables?tercero_id='.$this->supplier->toRfc4122());
        self::assertResponseStatusCodeSame(404, 'Company A’s supplier is unknown here.');

        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($mine, '100.00', [[self::payableOf($invoice), '100.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['allocations.0.payable_id'], array_column($body['violations'], 'field'), 'Company A’s payable is refused as unknown.');
        $body = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($this->supplier, '100.00', [[self::payableOf($invoice), '100.00']]));
        self::assertResponseStatusCodeSame(422);
        self::assertContains('tercero_id', array_column($body['violations'], 'field'), 'Company A’s supplier is unknown too.');

        $this->getJson('/api/v1/supplier-payments/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    private function html(string $paymentId): string
    {
        $this->em()->clear();
        $payment = $this->em()->find(SupplierPayment::class, Uuid::fromString($paymentId));
        \assert($payment instanceof SupplierPayment);

        return static::getContainer()->get(Environment::class)->render(SupplierPaymentPdf::TEMPLATE, static::getContainer()->get(SupplierPaymentPdf::class)->context($payment));
    }
}
