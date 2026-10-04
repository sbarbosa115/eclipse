<?php

namespace App\Tests\Functional\Sales;

use App\Sales\Application\Collection\InvoiceCollections;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\CashReceiptAllocation;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Infrastructure\Persistence\DoctrineReceivableRepository;
use App\Sales\Infrastructure\Persistence\DoctrineSalesInvoiceRepository;
use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Anulación (§4.12): while nothing is applied, dated today and after the lock date, a reversing entry, the receivables
 * out of the cartera, the number kept, the reason and who recorded; the PDF says ANULADA.
 */
final class VoidSalesInvoiceApiTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testVoidingPostsTheReversingEntryAndKeepsTheNumber(): void
    {
        $invoice = $this->emitted([$this->cash('190000.00'), $this->credit('1000000.00')]);

        $voided = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Precio equivocado']);

        self::assertResponseIsSuccessful();
        self::assertSame(['voided', 'FE-1', 'Precio equivocado'], [$voided['status'], $voided['number'], $voided['void_reason']]);
        self::assertNotNull($voided['voided_by']);
        self::assertNotNull($voided['voided_at']);
        self::assertSame('0.00', $voided['balance']);
        self::assertTrue($voided['receivables'][0]['voided'], 'The receivable leaves the cartera.');

        [$original, $reversal] = $this->entries();
        self::assertSame($voided['reversal_entry_id'], $reversal->id()->toRfc4122());
        self::assertTrue($original->id()->equals($reversal->reversesId() ?? Uuid::v7()));
        self::assertSame(self::today(), $reversal->entryDate()->format('Y-m-d'), 'Dated the void date.');
        self::assertSame([
            ['11050501', '0.00', '190000.00'],
            ['13050501', '0.00', '1000000.00'],
            ['413595', '1000000.00', '0.00'],
            ['240805', '190000.00', '0.00'],
        ], self::movements($reversal), 'The mirror of the A.1 entry.');
        self::assertSame('Anulación factura de venta FE-1: Precio equivocado', $reversal->description());

        $next = $this->emitted();
        self::assertSame('FE-2', $next['number'], 'AC-7: the number is not reused.');
    }

    public function testTheVoidLocksTheInvoiceSoAReceiptCannotSlipInMeanwhile(): void
    {
        $invoice = $this->emitted([$this->credit('1190000.00')]);

        $this->client->enableProfiler();
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Precio equivocado']);

        self::assertResponseIsSuccessful();
        $profile = $this->client->getProfile();
        self::assertInstanceOf(\Symfony\Component\HttpKernel\Profiler\Profile::class, $profile, 'The profiler collected this request.');
        /** @var \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $db */
        $db = $profile->getCollector('db');
        $locks = array_filter(array_merge(...array_values($db->getQueries())), static fn (array $q) => str_contains($q['sql'], 'sales_invoice') && str_contains($q['sql'], 'FOR UPDATE'));
        self::assertNotEmpty($locks, 'A cash receipt locks the invoices it pays; the void must take the same lock, or both pass the allocation check at once.');
    }

    public function testAVoidNeedsAReason(): void
    {
        $invoice = $this->emitted();

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('reason', $body['violations'][0]['field']);
        self::assertSame('Escribe el motivo de la anulación.', $body['violations'][0]['message']);
    }

    public function testADraftOrAVoidedInvoiceIsNotVoided(): void
    {
        $draft = $this->draft($this->payload($this->client('Otro', number: '811111111'), $this->service('X')));
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/void', ['reason' => 'x']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_emitted', $this->body()['error']);

        $invoice = $this->emitted();
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);
        self::assertResponseStatusCodeSame(409, 'Voided once.');
        self::assertCount(2, $this->entries(), 'One entry and one reversal.');
    }

    public function testAnInvoiceWithAReceiptAppliedIsNotVoided(): void
    {
        $invoice = $this->emitted([$this->credit('1190000.00')]);
        $this->receipt($invoice, '100000.00');

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_has_allocations', $body['error'], '§4.12: void the receipt first.');
        self::assertSame('partially_paid', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status']);
    }

    public function testAVoidedReceiptNoLongerBlocksTheVoid(): void
    {
        $invoice = $this->emitted([$this->credit('1190000.00')]);
        $receipt = $this->receipt($invoice, '100000.00');
        $this->undo($invoice, $receipt, '100000.00');

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);

        self::assertResponseIsSuccessful();
    }

    public function testNothingIsVoidedWhileTodayIsLocked(): void
    {
        // AC-9: the reversal would be dated today.
        $invoice = $this->emitted();
        $this->lockBooks(self::today());

        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'x']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error']);
        self::assertSame('paid', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status'], 'Nothing changed.');
    }

    public function testTheVoidedPdfSaysAnulada(): void
    {
        $invoice = $this->emitted();
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Cliente equivocado']);

        $html = $this->html($invoice['id']);

        self::assertStringContainsString('<div class="voided">ANULADA</div>', $html, '§4.12: the PDF shows ANULADA.');
        self::assertStringContainsString('Motivo: Cliente equivocado', $html);
        $this->client->request('GET', '/api/v1/sales-invoices/'.$invoice['id'].'/pdf');
        self::assertResponseIsSuccessful();
    }

    public function testThePdfCarriesWhatSection48Lists(): void
    {
        $invoice = $this->emitted();

        $html = $this->html($invoice['id']);

        foreach (['Acme S.A.S.', 'NIT 900123456', 'Resolución DIAN No. 18764000001234', 'FE-1 al FE-1000', 'No. FE-1', 'Cliente Uno S.A.S.', 'NIT 800197268', 'Consultoría', '$ 1.000.000,00', 'IVA 19 %', '$ 190.000,00', '$ 1.190.000,00', 'Efectivo'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('ANULADA', $html);
    }

    private function html(string $invoiceId): string
    {
        $this->em()->clear();
        $invoice = $this->em()->find(SalesInvoice::class, Uuid::fromString($invoiceId));
        \assert($invoice instanceof SalesInvoice);

        return static::getContainer()->get(Environment::class)->render(SalesInvoicePdf::TEMPLATE, static::getContainer()->get(SalesInvoicePdf::class)->context($invoice));
    }

    /**
     * A recibo de caja applied to the invoice's receivable, as the "cash-receipt" item will record it.
     *
     * @param array<string, mixed> $invoice
     */
    private function receipt(array $invoice, string $amount): CashReceipt
    {
        // Read before the transaction: a request resets the test's connection.
        $method = Uuid::fromString($this->methodId('Efectivo'));
        $caja = Uuid::fromString($this->accountId('11050501'));
        $em = $this->em();
        $em->clear();
        $connection = $em->getConnection();
        $connection->beginTransaction();
        $receivableId = Uuid::fromString($invoice['receivables'][0]['id']);
        $receipt = new CashReceipt($this->company, 'RC', 1, Uuid::fromString($invoice['tercero_id']), 'Cliente', new \DateTimeImmutable(self::today()), $method, 'Efectivo', $caja, Money::of($amount), null, Uuid::v7(), new \DateTimeImmutable());
        $em->persist($receipt);
        $em->persist(new CashReceiptAllocation($receipt, $this->company, $receivableId, Uuid::fromString($invoice['id']), $invoice['number'], Money::of($amount)));
        $this->collections()->apply($this->company, $receivableId, Money::of($amount));
        $em->flush();
        $connection->commit();
        $em->clear();

        return $receipt;
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function undo(array $invoice, CashReceipt $receipt, string $amount): void
    {
        $em = $this->em();
        $connection = $em->getConnection();
        $connection->beginTransaction();
        $connection->update('cash_receipt', ['status' => 'voided'], ['id' => $receipt->id()->toBinary()]);
        $this->collections()->unapply($this->company, Uuid::fromString($invoice['receivables'][0]['id']), Money::of($amount));
        $em->flush();
        $connection->commit();
        $em->clear();
    }

    private function collections(): InvoiceCollections
    {
        return new InvoiceCollections(new DoctrineReceivableRepository($this->em()), new DoctrineSalesInvoiceRepository($this->em()));
    }
}
