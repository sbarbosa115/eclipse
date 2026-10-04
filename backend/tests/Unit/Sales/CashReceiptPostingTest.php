<?php

namespace App\Tests\Unit\Sales;

use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Sales\Application\Posting\CashReceiptPosting;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Model\ReceivableAllocation;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The entry of a recibo de caja, PRD Appendix A.2: Dr the account where the money comes in (the contado method's,
 * 110505 / 111005); Cr 1305 Clientes with the client as tercero, one line per allocation, so the client's own
 * receivable account (§4.2) applies and cartera reads each invoice's collection.
 */
final class CashReceiptPostingTest extends TestCase
{
    public function testDebitTheMethodsAccountAndCreditClientesPerAllocation(): void
    {
        $company = Uuid::v7();
        $client = Uuid::v7();
        $caja = Uuid::v7();
        $user = Uuid::v7();
        $receivable = static fn (string $n) => new Receivable($company, Uuid::v7(), $n, $client, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-10-01'), Money::of('1000000'));
        $receipt = CashReceipt::issue(
            $company, 'RC', 12, $client, 'Cliente Uno S.A.S.', new \DateTimeImmutable('2026-10-02'),
            Uuid::v7(), 'Efectivo', $caja, Money::of('750000.00'), null,
            [new ReceivableAllocation($receivable('FE-1'), Money::of('500000.00')), new ReceivableAllocation($receivable('FE-2'), Money::of('250000.00'))],
            new \DateTimeImmutable('2026-10-03'), $user, new \DateTimeImmutable(),
        );

        $draft = CashReceiptPosting::entryFor($receipt, $user);

        self::assertSame('cash_receipt', $draft->sourceType, 'The libro diario links the entry to the receipt.');
        self::assertTrue($receipt->id()->equals($draft->sourceId));
        self::assertSame('RC-12', $draft->sourceNumber);
        self::assertSame('2026-10-02', $draft->date->format('Y-m-d'), 'Dated the receipt’s date.');
        self::assertSame('Recibo de caja RC-12 · Cliente Uno S.A.S.', $draft->description);

        $lines = array_map(static fn (EntryLine $l) => [
            $l->side, $l->amount->toString(), $l->accountId?->toRfc4122(), $l->concept, $l->terceroId?->toRfc4122(), $l->description,
        ], $draft->lines);
        self::assertSame([
            [Side::Debit, '750000.00', $caja->toRfc4122(), null, $client->toRfc4122(), null],
            [Side::Credit, '500000.00', null, PostingConcept::Receivables, $client->toRfc4122(), 'FE-1'],
            [Side::Credit, '250000.00', null, PostingConcept::Receivables, $client->toRfc4122(), 'FE-2'],
        ], $lines, 'Dr the method’s account for the amount; Cr clientes (tercero) once per invoice paid.');
    }
}
