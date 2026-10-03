<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Domain\Error\EntryEmpty;
use App\Ledger\Domain\Error\EntryUnbalanced;
use App\Ledger\Domain\Model\JournalEntry;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class JournalEntryTest extends TestCase
{
    private function entry(): JournalEntry
    {
        return new JournalEntry(Uuid::v7(), 1, new \DateTimeImmutable('2026-10-03'), 'sales_invoice', Uuid::v7(), 'FE-1', 'Factura FE-1', null, Uuid::v7(), new \DateTimeImmutable());
    }

    public function testABalancedEntryCloses(): void
    {
        $entry = $this->entry();
        $entry->debit(Uuid::v7(), '11050501', null, Money::of('1190000.00'));
        $entry->credit(Uuid::v7(), '413595', null, Money::of('1000000.00'));
        $entry->credit(Uuid::v7(), '240805', null, Money::of('190000.00'));

        $entry->close();

        self::assertSame('1190000.00', $entry->totalDebit()->toString());
        self::assertSame([1, 2, 3], array_map(static fn ($l) => $l->position(), $entry->lines()));
    }

    public function testAnEntryOffByOneCentIsRefused(): void
    {
        $entry = $this->entry();
        $entry->debit(Uuid::v7(), '11050501', null, Money::of('100.00'));
        $entry->credit(Uuid::v7(), '413595', null, Money::of('99.99'));

        try {
            $entry->close();
            self::fail('§5 invariant 1: every entry balances to the cent.');
        } catch (EntryUnbalanced $e) {
            self::assertSame(['debit' => '100.00', 'credit' => '99.99'], $e->details());
        }
    }

    public function testAnEntryWithoutMovementsIsRefused(): void
    {
        $this->expectException(EntryEmpty::class);

        $this->entry()->close();
    }

    public function testAMovementIsNeverNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->entry()->debit(Uuid::v7(), '11050501', null, Money::of('-1.00'));
    }
}
