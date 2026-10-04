<?php

namespace App\Tests\Unit\Purchasing;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\InvalidPayment;
use App\Purchasing\Domain\Error\PaymentAlreadyVoided;
use App\Purchasing\Domain\Error\PaymentAllocationsDoNotMatchAmount;
use App\Purchasing\Domain\Model\Payable;
use App\Purchasing\Domain\Model\PayableAllocation;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Shared\Domain\Model\ReceiptStatus;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de pago (§4.11) is emitted when it is saved: the money paid to a supplier, allocated to that supplier's open
 * payables, no more than each one's balance, adding up exactly to the amount (§9 Q16: no anticipos, no over-payment).
 * Voiding it (§4.12) keeps its number and records why.
 */
final class SupplierPaymentTest extends TestCase
{
    private Uuid $company;
    private Uuid $supplier;
    private Uuid $user;

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
        $this->supplier = Uuid::v7();
        $this->user = Uuid::v7();
    }

    private function payable(string $amount, ?Uuid $supplier = null, string $number = 'FC-1'): Payable
    {
        return new Payable($this->company, Uuid::v7(), $number, $supplier ?? $this->supplier, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-10-01'), Money::of($amount));
    }

    /**
     * @param list<PayableAllocation> $allocations
     */
    private function pay(string $amount, array $allocations, string $date = '2026-10-03', string $today = '2026-10-03'): SupplierPayment
    {
        return SupplierPayment::issue(
            $this->company, 'RP', 7, $this->supplier, 'Proveedor Uno S.A.S.', new \DateTimeImmutable($date),
            Uuid::v7(), 'Efectivo', Uuid::v7(), Money::of($amount), 'Abono',
            $allocations, new \DateTimeImmutable($today), $this->user, new \DateTimeImmutable('2026-10-03 10:00'),
        );
    }

    private static function to(Payable $payable, string $amount): PayableAllocation
    {
        return new PayableAllocation($payable, Money::of($amount));
    }

    /**
     * @return list<string> the fields an InvalidPayment names
     */
    private static function fieldsOf(callable $work): array
    {
        try {
            $work();
        } catch (InvalidPayment $e) {
            return array_map(static fn (array $v) => $v['field'], $e->violations());
        }
        self::fail('The payment was expected to be refused field by field.');
    }

    public function testAPaymentIsEmittedWithItsNumberAndAllocations(): void
    {
        $first = $this->payable('595000.00', number: 'FC-1');
        $second = $this->payable('300000.00', number: 'FC-2');

        $payment = $this->pay('695000.00', [self::to($first, '595000.00'), self::to($second, '100000.00')]);

        self::assertSame(ReceiptStatus::Emitted, $payment->status(), 'There is no draft: saving emits it (§4.11).');
        self::assertSame('RP-7', $payment->number());
        self::assertTrue($this->user->equals($payment->emittedBy()));
        self::assertCount(2, $payment->allocations());
        self::assertSame(['FC-1', '595000.00'], [$payment->allocations()[0]->invoiceNumber(), $payment->allocations()[0]->amount()->toString()]);
        self::assertTrue($second->id()->equals($payment->allocations()[1]->openItemId()), 'An allocation points at the payable it pays.');
        self::assertTrue($second->invoiceId()->equals($payment->allocations()[1]->invoiceId()));
        self::assertSame('595000.00', $first->balance()->toString(), 'The payment does not move balances itself: PayableAllocations does, in the handler.');
    }

    public function testTheAllocationsMustAddUpToTheAmountExactly(): void
    {
        $payable = $this->payable('595000.00');

        try {
            $this->pay('600000.00', [self::to($payable, '595000.00')]);
            self::fail('§9 Q16: no anticipo or over-payment, the 5.000 left over has nowhere to go.');
        } catch (PaymentAllocationsDoNotMatchAmount $e) {
            self::assertSame('allocations_do_not_match_amount', $e->errorCode());
            self::assertSame(['allocated_total' => '595000.00', 'amount' => '600000.00'], $e->details());
        }

        $this->expectException(PaymentAllocationsDoNotMatchAmount::class);
        $this->pay('500000.00', [self::to($payable, '400000.00')]);
    }

    public function testAnAllocationNeverExceedsThePayablesBalance(): void
    {
        $payable = $this->payable('595000.00');
        $payable->allocate(Money::of('500000.00'));

        self::assertSame('95000.00', $this->pay('95000.00', [self::to($payable, '95000.00')])->amount()->toString(), 'The balance left is payable.');

        $this->expectException(AllocationExceedsBalance::class);
        $this->pay('95000.01', [self::to($payable, '95000.01')]);
    }

    public function testTheAmountIsPositiveAndTheDateIsNotInTheFuture(): void
    {
        $payable = $this->payable('595000.00');

        self::assertSame(['amount'], self::fieldsOf(fn () => $this->pay('0.00', [self::to($payable, '0.01')])), 'Valor pagado > 0.');
        self::assertSame(['receipt_date'], self::fieldsOf(fn () => $this->pay('100.00', [self::to($payable, '100.00')], date: '2026-10-04', today: '2026-10-03')));
        self::assertSame('2026-10-01', $this->pay('100.00', [self::to($payable, '100.00')], date: '2026-10-01')->receiptDate()->format('Y-m-d'), 'Backdating within the open period is allowed (§9 Q11).');
    }

    public function testThereIsAlwaysSomethingToPayAndEachAllocationIsPositive(): void
    {
        $payable = $this->payable('595000.00');

        self::assertSame(['allocations'], self::fieldsOf(fn () => $this->pay('100.00', [])), 'No anticipos (§9 Q16).');
        self::assertSame(['allocations.1.amount'], self::fieldsOf(fn () => $this->pay('100.00', [self::to($payable, '100.00'), self::to($this->payable('10.00'), '0.00')])));
    }

    public function testOnlyTheSuppliersOwnOpenPayablesAndEachOnce(): void
    {
        $mine = $this->payable('595000.00');
        $someoneElses = $this->payable('100.00', Uuid::v7());
        $voided = $this->payable('100.00');
        $voided->void();

        self::assertSame(['allocations.1.payable_id'], self::fieldsOf(fn () => $this->pay('200.00', [self::to($mine, '100.00'), self::to($someoneElses, '100.00')])));
        self::assertSame(['allocations.0.payable_id'], self::fieldsOf(fn () => $this->pay('100.00', [self::to($voided, '100.00')])), 'A voided invoice is owed nothing.');
        self::assertSame(['allocations.1.payable_id'], self::fieldsOf(fn () => $this->pay('200.00', [self::to($mine, '100.00'), self::to($mine, '100.00')])), 'One row per payable.');
    }

    public function testVoidingKeepsTheNumberAndRecordsWhy(): void
    {
        $payment = $this->pay('100.00', [self::to($this->payable('595000.00'), '100.00')]);

        $payment->void('Transferencia rechazada', $this->user, new \DateTimeImmutable('2026-10-04 08:00'));

        self::assertSame(ReceiptStatus::Voided, $payment->status());
        self::assertSame('RP-7', $payment->number(), '§4.12: the number is kept, never reused.');
        self::assertSame('Transferencia rechazada', $payment->voidReason());
        self::assertTrue($this->user->equals($payment->voidedBy()));
        self::assertSame('2026-10-04 08:00', $payment->voidedAt()?->format('Y-m-d H:i'));
    }

    public function testAVoidNeedsAReasonAndHappensOnce(): void
    {
        $payment = $this->pay('100.00', [self::to($this->payable('595000.00'), '100.00')]);

        self::assertSame(['reason'], self::fieldsOf(fn () => $payment->void('   ', $this->user, new \DateTimeImmutable())));

        $payment->void('Error de digitación', $this->user, new \DateTimeImmutable());
        $this->expectException(PaymentAlreadyVoided::class);
        $payment->void('Otra vez', $this->user, new \DateTimeImmutable());
    }

    public function testTheEntriesAreRecorded(): void
    {
        $payment = $this->pay('100.00', [self::to($this->payable('595000.00'), '100.00')]);
        $entry = Uuid::v7();
        $reversal = Uuid::v7();

        $payment->recordPosting($entry);
        $payment->void('Error', $this->user, new \DateTimeImmutable());
        $payment->recordReversal($reversal);

        self::assertTrue($entry->equals($payment->journalEntryId()));
        self::assertTrue($reversal->equals($payment->reversalEntryId()), 'A void adds a second, reversing entry (§3).');
    }
}
