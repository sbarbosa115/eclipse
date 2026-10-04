<?php

namespace App\Tests\Unit\Sales;

use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Sales\Domain\Error\AllocationsDoNotMatchAmount;
use App\Sales\Domain\Error\InvalidReceipt;
use App\Sales\Domain\Error\ReceiptAlreadyVoided;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Model\ReceivableAllocation;
use App\Shared\Domain\Model\ReceiptStatus;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de caja (§4.9) is emitted when it is saved: the money received from a client, allocated to that client's
 * open receivables, no more than each one's balance, adding up exactly to the amount (§9 Q16: no anticipos, no
 * over-payment). Voiding it (§4.12) keeps its number and records why.
 */
final class AllocationTest extends TestCase
{
    private Uuid $company;
    private Uuid $client;
    private Uuid $user;

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
        $this->client = Uuid::v7();
        $this->user = Uuid::v7();
    }

    private function receivable(string $amount, ?Uuid $client = null, string $number = 'FE-1'): Receivable
    {
        return new Receivable($this->company, Uuid::v7(), $number, $client ?? $this->client, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-10-01'), Money::of($amount));
    }

    /**
     * @param list<ReceivableAllocation> $allocations
     */
    private function receive(string $amount, array $allocations, string $date = '2026-10-03', string $today = '2026-10-03'): CashReceipt
    {
        return CashReceipt::issue(
            $this->company, 'RC', 7, $this->client, 'Cliente Uno S.A.S.', new \DateTimeImmutable($date),
            Uuid::v7(), 'Efectivo', Uuid::v7(), Money::of($amount), 'Abono',
            $allocations, new \DateTimeImmutable($today), $this->user, new \DateTimeImmutable('2026-10-03 10:00'),
        );
    }

    private static function to(Receivable $receivable, string $amount): ReceivableAllocation
    {
        return new ReceivableAllocation($receivable, Money::of($amount));
    }

    /**
     * @return list<string> the fields an InvalidReceipt names
     */
    private static function fieldsOf(callable $work): array
    {
        try {
            $work();
        } catch (InvalidReceipt $e) {
            return array_map(static fn (array $v) => $v['field'], $e->violations());
        }
        self::fail('The receipt was expected to be refused field by field.');
    }

    public function testAReceiptIsEmittedWithItsNumberAndAllocations(): void
    {
        $first = $this->receivable('595000.00', number: 'FE-1');
        $second = $this->receivable('300000.00', number: 'FE-2');

        $receipt = $this->receive('695000.00', [self::to($first, '595000.00'), self::to($second, '100000.00')]);

        self::assertSame(ReceiptStatus::Emitted, $receipt->status(), 'There is no draft: saving emits it (§4.9).');
        self::assertSame('RC-7', $receipt->number());
        self::assertTrue($this->user->equals($receipt->emittedBy()), 'Who emitted it is recorded (§4.14).');
        self::assertCount(2, $receipt->allocations());
        self::assertSame(['FE-1', '595000.00'], [$receipt->allocations()[0]->invoiceNumber(), $receipt->allocations()[0]->amount()->toString()]);
        self::assertTrue($second->id()->equals($receipt->allocations()[1]->openItemId()), 'An allocation points at the receivable it pays.');
        self::assertTrue($second->invoiceId()->equals($receipt->allocations()[1]->invoiceId()), 'And at its invoice.');
        self::assertSame('595000.00', $first->balance()->toString(), 'The receipt does not move balances itself: InvoiceCollections does, in the handler.');
    }

    public function testTheAllocationsMustAddUpToTheAmountExactly(): void
    {
        $receivable = $this->receivable('595000.00');

        try {
            $this->receive('600000.00', [self::to($receivable, '595000.00')]);
            self::fail('§9 Q16: no anticipo or over-payment, the 5.000 left over has nowhere to go.');
        } catch (AllocationsDoNotMatchAmount $e) {
            self::assertSame('allocations_do_not_match_amount', $e->errorCode());
            self::assertSame(['allocated_total' => '595000.00', 'amount' => '600000.00'], $e->details(), 'The UI shows both sums.');
        }

        $this->expectException(AllocationsDoNotMatchAmount::class);
        $this->receive('500000.00', [self::to($receivable, '400000.00')]);
    }

    public function testAnAllocationNeverExceedsTheReceivablesBalance(): void
    {
        $receivable = $this->receivable('595000.00');

        $this->expectException(AllocationExceedsBalance::class);
        $this->receive('595000.01', [self::to($receivable, '595000.01')]);
    }

    public function testAPartlyPaidReceivableTakesOnlyWhatIsLeft(): void
    {
        $receivable = $this->receivable('595000.00');
        $receivable->apply(Money::of('500000.00'));

        $receipt = $this->receive('95000.00', [self::to($receivable, '95000.00')]);
        self::assertSame('95000.00', $receipt->amount()->toString(), 'The balance left is collectable.');

        $this->expectException(AllocationExceedsBalance::class);
        $this->receive('95000.01', [self::to($receivable, '95000.01')]);
    }

    public function testTheAmountIsPositiveAndTheDateIsNotInTheFuture(): void
    {
        $receivable = $this->receivable('595000.00');

        self::assertSame(['amount'], self::fieldsOf(fn () => $this->receive('0.00', [self::to($receivable, '0.01')])), 'Valor recibido > 0.');
        self::assertSame(['receipt_date'], self::fieldsOf(fn () => $this->receive('100.00', [self::to($receivable, '100.00')], date: '2026-10-04', today: '2026-10-03')), 'Money is received today or before, never tomorrow.');
        self::assertSame('2026-10-01', $this->receive('100.00', [self::to($receivable, '100.00')], date: '2026-10-01')->receiptDate()->format('Y-m-d'), 'Backdating within the open period is allowed (§9 Q11).');
    }

    public function testThereIsAlwaysSomethingToPayAndEachAllocationIsPositive(): void
    {
        $receivable = $this->receivable('595000.00');

        self::assertSame(['allocations'], self::fieldsOf(fn () => $this->receive('100.00', [])), 'No anticipos: money received pays an invoice (§9 Q16).');
        self::assertSame(['allocations.1.amount'], self::fieldsOf(fn () => $this->receive('100.00', [self::to($receivable, '100.00'), self::to($this->receivable('10.00'), '0.00')])), 'A row allocated nothing.');
    }

    public function testOnlyTheClientsOwnOpenReceivablesAndEachOnce(): void
    {
        $mine = $this->receivable('595000.00');
        $someoneElses = $this->receivable('100.00', Uuid::v7());
        $voided = $this->receivable('100.00');
        $voided->void();

        self::assertSame(['allocations.1.receivable_id'], self::fieldsOf(fn () => $this->receive('200.00', [self::to($mine, '100.00'), self::to($someoneElses, '100.00')])), "Another client's invoice is not paid by this client's receipt.");
        self::assertSame(['allocations.0.receivable_id'], self::fieldsOf(fn () => $this->receive('100.00', [self::to($voided, '100.00')])), 'A voided invoice is owed nothing.');
        self::assertSame(['allocations.1.receivable_id'], self::fieldsOf(fn () => $this->receive('200.00', [self::to($mine, '100.00'), self::to($mine, '100.00')])), 'One row per receivable.');
    }

    public function testVoidingKeepsTheNumberAndRecordsWhy(): void
    {
        $receipt = $this->receive('100.00', [self::to($this->receivable('595000.00'), '100.00')]);

        $receipt->void('Cheque devuelto', $this->user, new \DateTimeImmutable('2026-10-04 08:00'));

        self::assertSame(ReceiptStatus::Voided, $receipt->status());
        self::assertSame('RC-7', $receipt->number(), '§4.12: the number is kept, never reused.');
        self::assertSame('Cheque devuelto', $receipt->voidReason());
        self::assertTrue($this->user->equals($receipt->voidedBy()));
        self::assertSame('2026-10-04 08:00', $receipt->voidedAt()?->format('Y-m-d H:i'));
    }

    public function testAVoidNeedsAReasonAndHappensOnce(): void
    {
        $receipt = $this->receive('100.00', [self::to($this->receivable('595000.00'), '100.00')]);

        self::assertSame(['reason'], self::fieldsOf(fn () => $receipt->void('   ', $this->user, new \DateTimeImmutable())), 'The reason is recorded with the void (§4.12).');

        $receipt->void('Error de digitación', $this->user, new \DateTimeImmutable());
        $this->expectException(ReceiptAlreadyVoided::class);
        $receipt->void('Otra vez', $this->user, new \DateTimeImmutable());
    }

    public function testTheEntriesAreRecorded(): void
    {
        $receipt = $this->receive('100.00', [self::to($this->receivable('595000.00'), '100.00')]);
        $entry = Uuid::v7();
        $reversal = Uuid::v7();

        $receipt->recordPosting($entry);
        $receipt->void('Error', $this->user, new \DateTimeImmutable());
        $receipt->recordReversal($reversal);

        self::assertTrue($entry->equals($receipt->journalEntryId()));
        self::assertTrue($reversal->equals($receipt->reversalEntryId()), 'A void adds a second, reversing entry (§3).');
    }
}
