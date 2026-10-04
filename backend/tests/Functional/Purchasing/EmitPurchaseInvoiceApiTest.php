<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Domain\Model\Payable;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §4.10 and §4.12 through the API, against the real ledger: emission numbers the invoice (FC), opens the payables and
 * posts Appendix A.3 in the same transaction; a refusal leaves the draft and spends no number; a void posts the
 * reversing entry dated today.
 */
final class EmitPurchaseInvoiceApiTest extends ApiTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    /** @return list<Payable> */
    private function payables(string $invoiceId): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(Payable::class)->findBy(['companyId' => $this->company, 'invoiceId' => Uuid::fromString($invoiceId)]);
    }

    public function testEmittingNumbersPostsAndOpensThePayables(): void
    {
        $invoice = $this->emitInvoice(['payments' => [
            ['payment_method_id' => $this->methodId('Efectivo'), 'amount' => '150000.00', 'due_date' => null],
            ['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1000000.00', 'due_date' => self::today(29)],
        ]]);

        self::assertSame('emitted', $invoice['status']);
        self::assertSame('FC-1', $invoice['number'], 'The first purchase invoice of the series FC.');
        self::assertNotNull($invoice['emitted_at']);
        self::assertNotNull($invoice['journal_entry_id']);

        [$entry] = $this->entries();
        self::assertSame('purchase_invoice', $entry->sourceType());
        self::assertSame('FC-1', $entry->sourceNumber());
        self::assertSame(self::today(-1), $entry->entryDate()->format('Y-m-d'), 'Posted on the invoice\'s date.');
        $supplier = $this->supplier->toRfc4122();
        self::assertSame([
            ['513595', '1000000.00', '0.00', null],
            ['240810', '190000.00', '0.00', null],
            ['236525', '0.00', '40000.00', $supplier],
            ['11050501', '0.00', '150000.00', null],
            ['22050501', '0.00', '1000000.00', $supplier],
        ], self::movements($entry), 'A.3: Dr gasto and IVA descontable; Cr ReteFuente servicios, caja and proveedores.');

        $payables = $this->payables($invoice['id']);
        self::assertCount(1, $payables, 'Only the crédito line is owed.');
        self::assertSame('1000000.00', $payables[0]->balance()->toString());
        self::assertSame(self::today(29), $payables[0]->dueDate()->format('Y-m-d'));
        self::assertSame([['amount' => '1000000.00', 'balance' => '1000000.00', 'due_date' => self::today(29), 'voided' => false]], array_map(static fn (array $p) => array_diff_key($p, ['id' => 1]), $invoice['payables']));
    }

    public function testNumbersFollowEachOther(): void
    {
        $this->emitInvoice();
        $second = $this->emitInvoice(['supplier_invoice_number' => 'FAC-882']);

        self::assertSame('FC-2', $second['number']);
    }

    public function testAnEmittedInvoiceCannotBeChangedOrEmittedAgain(): void
    {
        $invoice = $this->emitInvoice();

        $body = $this->sendJson('PUT', "/api/v1/purchase-invoices/{$invoice['id']}", $this->payload());
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_draft', $body['error']);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/emit", []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_draft', $body['error']);
    }

    public function testPaymentsThatDoNotAddUpToTheTotalAreRefused(): void
    {
        $draft = $this->createDraft(['payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1000000.00', 'due_date' => self::today(29)]]]);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('payments_do_not_match_total', $body['error']);
        self::assertSame(['payments_total' => '1000000.00', 'net_total' => '1150000.00'], $body['detail']);
        self::assertSame([], $this->entries(), 'Nothing is posted.');
        self::assertSame('draft', $this->getJson("/api/v1/purchase-invoices/{$draft['id']}")['status']);
    }

    public function testTheSupplierNumberIsNeededToEmit(): void
    {
        $draft = $this->createDraft(['supplier_invoice_number' => null]);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['supplier_invoice_number'], self::violationFields($body));
    }

    public function testADateInTheFutureIsRefused(): void
    {
        $draft = $this->createDraft(['issue_date' => self::today(1)]);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('issue_date_in_future', $body['error']);
    }

    public function testADateOnOrBeforeTheLockDateIsRefusedAndSpendsNoNumber(): void
    {
        $draft = $this->createDraft();
        $this->lockBooks(self::today(-1));

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'Acceptance criterion 9.');
        self::assertSame([], $this->payables($draft['id']));

        $this->lockBooks(self::today(-2));
        self::assertSame('FC-1', $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", [])['number'], 'The refused emission gave its number back.');
    }

    public function testAnInactiveSupplierCannotBeEmittedTo(): void
    {
        $draft = $this->createDraft();
        $this->db()->update('tercero', ['active' => 0], ['id' => $this->supplier->toBinary()]);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('supplier_inactive', $body['error']);
    }

    public function testVoidingPostsTheReversingEntryTodayAndVoidsThePayables(): void
    {
        $invoice = $this->emitInvoice();

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/void", ['reason' => 'Registrada dos veces']);

        self::assertResponseIsSuccessful(json_encode($body) ?: '');
        self::assertSame('voided', $body['status']);
        self::assertSame('FC-1', $body['number'], 'The number is kept and never reused.');
        self::assertSame('Registrada dos veces', $body['void_reason']);
        self::assertNotNull($body['reversal_entry_id']);
        [$original, $reversal] = $this->entries();
        self::assertSame(self::today(), $reversal->entryDate()->format('Y-m-d'), 'The reversing entry is dated the void date.');
        self::assertTrue($original->id()->equals($reversal->reversesId() ?? Uuid::v7()));
        self::assertTrue($this->payables($invoice['id'])[0]->isVoided(), 'Nothing is owed on a voided invoice.');
        self::assertSame([], array_filter($body['payables'], static fn (array $p) => !$p['voided']));
    }

    public function testTheVoidLocksTheInvoiceSoAPaymentCannotSlipInMeanwhile(): void
    {
        $invoice = $this->emitInvoice();

        $this->client->enableProfiler();
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/void", ['reason' => 'Registrada dos veces']);

        self::assertResponseIsSuccessful();
        $profile = $this->client->getProfile();
        self::assertInstanceOf(\Symfony\Component\HttpKernel\Profiler\Profile::class, $profile, 'The profiler collected this request.');
        /** @var \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $db */
        $db = $profile->getCollector('db');
        $locks = array_filter(array_merge(...array_values($db->getQueries())), static fn (array $q) => str_contains($q['sql'], 'purchase_invoice') && str_contains($q['sql'], 'FOR UPDATE'));
        self::assertNotEmpty($locks, 'A supplier payment will lock the invoices it pays; the void must take the same lock.');
    }

    public function testAVoidNeedsAReason(): void
    {
        $invoice = $this->emitInvoice();

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/void", ['reason' => ' ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['reason'], self::violationFields($body));
    }

    public function testADraftCannotBeVoided(): void
    {
        $draft = $this->createDraft();

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/void", ['reason' => 'No aplica']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_emitted', $body['error']);
    }

    public function testAnInvoiceWithAPaymentAllocatedCannotBeVoided(): void
    {
        $invoice = $this->emitInvoice();
        $payable = $this->payables($invoice['id'])[0];
        $this->db()->update('payable', ['balance' => '1000000.00'], ['id' => $payable->id()->toBinary()]);
        $this->db()->update('purchase_invoice', ['paid_amount' => '150000.00', 'status' => 'partially_paid'], ['id' => Uuid::fromString($invoice['id'])->toBinary()]);

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/void", ['reason' => 'Error']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_has_allocations', $body['error']);
        self::assertCount(1, $this->entries(), 'No reversing entry.');
    }

    public function testTheAccountantCannotEmitOrVoid(): void
    {
        $draft = $this->createDraft();
        $this->signInAs('accountant');

        $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);
        self::assertResponseStatusCodeSame(403, 'The accountant cannot emit commercial documents (§8).');
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/void", ['reason' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheBillingUserEmits(): void
    {
        $draft = $this->createDraft();
        $this->signInAs('billing');

        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        self::assertResponseIsSuccessful();
        self::assertSame('emitted', $body['status']);
    }
}
