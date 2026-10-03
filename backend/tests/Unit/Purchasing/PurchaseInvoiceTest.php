<?php

namespace App\Tests\Unit\Purchasing;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\DocumentAlreadyVoided;
use App\Purchasing\Domain\Error\DocumentHasAllocations;
use App\Purchasing\Domain\Error\DocumentHasNoLines;
use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Error\DocumentNotEmitted;
use App\Purchasing\Domain\Error\IssueDateInFuture;
use App\Purchasing\Domain\Error\PaymentsDoNotMatchTotal;
use App\Purchasing\Domain\Error\SupplierInvoiceNumberRequired;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchasePaymentDraft;
use App\Shared\Domain\Model\InvoiceStatus;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §4.10 and §4.12: a factura de compra is edited only as a draft, emitted once (number, payables), voided only while
 * nothing is allocated to it, and paid through its payables.
 */
final class PurchaseInvoiceTest extends TestCase
{
    private Uuid $company;
    private Uuid $supplier;
    private Uuid $user;
    private Uuid $creditMethod;
    private Uuid $cashMethod;
    private Uuid $cashAccount;

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
        $this->supplier = Uuid::v7();
        $this->user = Uuid::v7();
        $this->creditMethod = Uuid::v7();
        $this->cashMethod = Uuid::v7();
        $this->cashAccount = Uuid::v7();
    }

    private static function date(string $ymd): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd);
    }

    private function draft(?string $supplierNumber = 'FAC-881', string $issueDate = '2026-10-01'): PurchaseInvoice
    {
        return new PurchaseInvoice($this->company, $this->supplier, 'Servicios Andinos S.A.S.', $supplierNumber, self::date($issueDate), $this->user, self::date('2026-10-01 09:00'));
    }

    /** A service of 1 000 000 with IVA 19 % and ReteFuente 4 %: total neto 1 150 000. */
    private static function serviceLine(?Uuid $account = null): PurchaseLineDraft
    {
        return new PurchaseLineDraft(
            null,
            $account ?? Uuid::v7(),
            'Mantenimiento de equipos',
            Quantity::of('1'),
            UnitPrice::of('1000000'),
            Rate::zero(),
            new TaxSnapshot(Uuid::v7(), 'IVA 19 %', 'iva', TaxCalculation::Percentage, '19.0000', null),
            new TaxSnapshot(Uuid::v7(), 'ReteFuente servicios 4 %', 'retefuente', TaxCalculation::Percentage, '4.0000', null),
        );
    }

    private function credit(string $amount, string $due = '2026-10-31'): PurchasePaymentDraft
    {
        return new PurchasePaymentDraft($this->creditMethod, 'Crédito', PaymentKind::Credit, null, Money::of($amount), self::date($due));
    }

    private function cash(string $amount): PurchasePaymentDraft
    {
        return new PurchasePaymentDraft($this->cashMethod, 'Efectivo', PaymentKind::Cash, $this->cashAccount, Money::of($amount), null);
    }

    /**
     * @param list<PurchasePaymentDraft>|null $payments
     */
    private function revise(PurchaseInvoice $invoice, ?array $payments = null, ?string $supplierNumber = 'FAC-881'): void
    {
        $invoice->revise($this->supplier, 'Servicios Andinos S.A.S.', $supplierNumber, self::date('2026-10-01'), self::date('2026-10-31'), 'Octubre', [self::serviceLine()], $payments ?? [$this->credit('1150000.00')]);
    }

    private function emitted(): PurchaseInvoice
    {
        $invoice = $this->draft();
        $this->revise($invoice);
        $invoice->emit('FC', 7, $this->user, self::date('2026-10-02 10:00'), self::date('2026-10-02'));

        return $invoice;
    }

    public function testADraftKeepsItsLinesPaymentsAndTotals(): void
    {
        $invoice = $this->draft();
        $this->revise($invoice);

        self::assertSame(InvoiceStatus::Draft, $invoice->status(), 'A new purchase invoice is a draft.');
        self::assertNull($invoice->number(), 'The internal number is taken at emission, not before.');
        self::assertCount(1, $invoice->lines());
        self::assertCount(1, $invoice->payments());
        self::assertSame('1000000.00', $invoice->subtotal()->toString());
        self::assertSame('190000.00', $invoice->taxTotal()->toString(), 'IVA descontable on the base.');
        self::assertSame('40000.00', $invoice->withholdingTotal()->toString(), 'ReteFuente practicada on the base.');
        self::assertSame('1150000.00', $invoice->netTotal()->toString(), 'Total neto = subtotal + IVA − retención.');
        self::assertSame('1150000.00', $invoice->lines()[0]->totalAmount()->toString(), 'The line carries its share of the totals.');
        self::assertSame('Octubre', $invoice->notes());
        self::assertSame('2026-10-31', $invoice->dueDate()?->format('Y-m-d'));
    }

    public function testRevisingReplacesTheLinesAndPayments(): void
    {
        $invoice = $this->draft();
        $this->revise($invoice);
        $this->revise($invoice, [$this->cash('1000000.00'), $this->credit('150000.00')]);

        self::assertCount(1, $invoice->lines(), 'The new lines replace the old ones.');
        self::assertCount(2, $invoice->payments(), 'The new payments replace the old ones.');
        self::assertSame(1, $invoice->payments()[1]->position(), 'Positions follow the order given.');
    }

    public function testADraftMayBeSavedWithoutTheSupplierNumberButNotEmitted(): void
    {
        $invoice = $this->draft(null);
        $this->revise($invoice, supplierNumber: null);
        self::assertNull($invoice->supplierInvoiceNumber());

        $this->expectException(SupplierInvoiceNumberRequired::class);
        $invoice->emit('FC', 1, $this->user, self::date('2026-10-02'), self::date('2026-10-02'));
    }

    public function testEmissionNumbersTheInvoiceAndCreatesOnePayablePerCreditLine(): void
    {
        $invoice = $this->draft();
        $this->revise($invoice, [$this->cash('150000.00'), $this->credit('500000.00', '2026-10-31'), $this->credit('500000.00', '2026-11-30')]);

        $payables = $invoice->emit('FC', 7, $this->user, self::date('2026-10-02 10:00'), self::date('2026-10-02'));

        self::assertSame(InvoiceStatus::Emitted, $invoice->status());
        self::assertSame('FC-7', $invoice->number(), 'The internal number is the series prefix and its consecutive.');
        self::assertSame(7, $invoice->sequence());
        self::assertTrue($this->user->equals($invoice->emittedBy() ?? Uuid::v7()), 'Who emitted it is recorded (§4.14).');
        self::assertCount(2, $payables, 'One payable per crédito line; contado creates none.');
        self::assertSame(['500000.00', '500000.00'], array_map(static fn ($p) => $p->amount()->toString(), $payables));
        self::assertSame(['2026-10-31', '2026-11-30'], array_map(static fn ($p) => $p->dueDate()->format('Y-m-d'), $payables), 'Each payable falls due on its line\'s date.');
        self::assertSame('FC-7', $payables[0]->invoiceNumber());
        self::assertTrue($this->supplier->equals($payables[0]->terceroId()), 'The payable is owed to the supplier.');
        self::assertSame('500000.00', $payables[0]->balance()->toString(), 'Nothing is paid yet.');
    }

    public function testAnEmittedInvoiceCannotBeEdited(): void
    {
        $invoice = $this->emitted();

        $this->expectException(DocumentNotDraft::class);
        $this->revise($invoice);
    }

    public function testAnInvoiceIsEmittedOnce(): void
    {
        $invoice = $this->emitted();

        $this->expectException(DocumentNotDraft::class);
        $invoice->emit('FC', 8, $this->user, self::date('2026-10-02'), self::date('2026-10-02'));
    }

    public function testEmissionNeedsALine(): void
    {
        $invoice = $this->draft();
        $invoice->revise($this->supplier, 'Servicios Andinos S.A.S.', 'FAC-881', self::date('2026-10-01'), null, null, [], []);

        $this->expectException(DocumentHasNoLines::class);
        $invoice->emit('FC', 1, $this->user, self::date('2026-10-02'), self::date('2026-10-02'));
    }

    public function testEmissionNeedsPaymentsAddingUpToTotalNeto(): void
    {
        $invoice = $this->draft();
        $this->revise($invoice, [$this->credit('1000000.00')]);

        try {
            $invoice->emit('FC', 1, $this->user, self::date('2026-10-02'), self::date('2026-10-02'));
            self::fail('Formas de pago of 1 000 000 against a total neto of 1 150 000 cannot be emitted.');
        } catch (PaymentsDoNotMatchTotal $e) {
            self::assertSame('payments_do_not_match_total', $e->errorCode());
            self::assertSame(['payments_total' => '1000000.00', 'net_total' => '1150000.00'], $e->details());
        }
        self::assertSame(InvoiceStatus::Draft, $invoice->status(), 'A refused emission leaves the draft as it was.');
    }

    public function testADateInTheFutureCannotBeEmitted(): void
    {
        $invoice = $this->draft(issueDate: '2026-10-05');
        $invoice->revise($this->supplier, 'Servicios Andinos S.A.S.', 'FAC-881', self::date('2026-10-05'), null, null, [self::serviceLine()], [$this->credit('1150000.00')]);

        $this->expectException(IssueDateInFuture::class);
        $invoice->emit('FC', 1, $this->user, self::date('2026-10-02'), self::date('2026-10-02'));
    }

    public function testVoidingKeepsTheNumberAndRecordsWhoAndWhy(): void
    {
        $invoice = $this->emitted();
        $invoice->recordEntry($entry = Uuid::v7());

        $invoice->void('Factura registrada dos veces', $this->user, self::date('2026-10-03 08:00'), $reversal = Uuid::v7());

        self::assertSame(InvoiceStatus::Voided, $invoice->status());
        self::assertSame('FC-7', $invoice->number(), 'The number is never reused (§4.12).');
        self::assertSame('Factura registrada dos veces', $invoice->voidReason());
        self::assertTrue($this->user->equals($invoice->voidedBy() ?? Uuid::v7()));
        self::assertTrue($entry->equals($invoice->journalEntryId() ?? Uuid::v7()), 'The original entry is kept.');
        self::assertTrue($reversal->equals($invoice->reversalEntryId() ?? Uuid::v7()), 'The reversing entry is recorded.');
    }

    public function testADraftCannotBeVoided(): void
    {
        $invoice = $this->draft();

        $this->expectException(DocumentNotEmitted::class);
        $invoice->assertVoidable();
    }

    public function testAVoidedInvoiceCannotBeVoidedAgain(): void
    {
        $invoice = $this->emitted();
        $invoice->void('Duplicada', $this->user, self::date('2026-10-03'), Uuid::v7());

        $this->expectException(DocumentAlreadyVoided::class);
        $invoice->assertVoidable();
    }

    public function testAnInvoiceWithPaymentsAllocatedCannotBeVoided(): void
    {
        $invoice = $this->emitted();
        $invoice->applyPayment(Money::of('100000.00'));

        try {
            $invoice->void('Error', $this->user, self::date('2026-10-03'), Uuid::v7());
            self::fail('A partly paid invoice cannot be voided: its payments must be voided first.');
        } catch (DocumentHasAllocations $e) {
            self::assertSame('document_has_allocations', $e->errorCode());
        }
        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->status());
    }

    public function testPaymentsMoveTheInvoiceToPartiallyPaidThenPaid(): void
    {
        $invoice = $this->emitted();

        $invoice->applyPayment(Money::of('150000.00'));
        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->status());
        self::assertSame('150000.00', $invoice->paidAmount()->toString());

        $invoice->applyPayment(Money::of('1000000.00'));
        self::assertSame(InvoiceStatus::Paid, $invoice->status(), 'Paid when the payments reach the total neto.');

        $invoice->unapplyPayment(Money::of('1000000.00'));
        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->status(), 'Voiding a payment opens the invoice again.');

        $invoice->unapplyPayment(Money::of('150000.00'));
        self::assertSame(InvoiceStatus::Emitted, $invoice->status(), 'With nothing paid it is just emitted.');
    }

    public function testAPaymentCannotExceedWhatIsOwed(): void
    {
        $invoice = $this->emitted();

        $this->expectException(AllocationExceedsBalance::class);
        $invoice->applyPayment(Money::of('1150000.01'));
    }

    public function testADraftCannotBePaid(): void
    {
        $invoice = $this->draft();
        $this->revise($invoice);

        $this->expectException(DocumentNotEmitted::class);
        $invoice->applyPayment(Money::of('1.00'));
    }

    public function testDuplicatingMakesADraftWithTheSameSupplierLinesAndTermsDatedToday(): void
    {
        $invoice = $this->emitted();

        $copy = $invoice->duplicate($this->user, self::date('2026-10-10 12:00'), self::date('2026-10-10'));

        self::assertSame(InvoiceStatus::Draft, $copy->status());
        self::assertFalse($copy->id()->equals($invoice->id()));
        self::assertNull($copy->number());
        self::assertNull($copy->supplierInvoiceNumber(), 'The supplier\'s number is the new invoice\'s own: it is typed again.');
        self::assertSame('2026-10-10', $copy->issueDate()->format('Y-m-d'));
        self::assertTrue($this->supplier->equals($copy->terceroId()));
        self::assertCount(1, $copy->lines());
        self::assertSame('1150000.00', $copy->netTotal()->toString());
        self::assertSame('2026-11-09', $copy->payments()[0]->dueDate()?->format('Y-m-d'), 'A 30-day term stays a 30-day term.');
        self::assertSame('2026-11-09', $copy->dueDate()?->format('Y-m-d'));
    }
}
