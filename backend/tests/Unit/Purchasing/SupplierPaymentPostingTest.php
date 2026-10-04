<?php

namespace App\Tests\Unit\Purchasing;

use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Purchasing\Application\Posting\SupplierPaymentPosting;
use App\Purchasing\Domain\Model\Payable;
use App\Purchasing\Domain\Model\PayableAllocation;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The entry of a recibo de pago, PRD Appendix A.4: Dr 2205 Proveedores with the supplier as tercero, one line per
 * allocation (so the supplier's own payable account applies and cartera reads each invoice's payment); Cr the account
 * the money leaves from (the contado method's, 110505 / 111005).
 */
final class SupplierPaymentPostingTest extends TestCase
{
    public function testDebitProveedoresPerAllocationAndCreditTheMethodsAccount(): void
    {
        $company = Uuid::v7();
        $supplier = Uuid::v7();
        $bancos = Uuid::v7();
        $user = Uuid::v7();
        $payable = static fn (string $n) => new Payable($company, Uuid::v7(), $n, $supplier, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-10-01'), Money::of('1000000'));
        $payment = SupplierPayment::issue(
            $company, 'RP', 12, $supplier, 'Proveedor Uno S.A.S.', new \DateTimeImmutable('2026-10-02'),
            Uuid::v7(), 'Transferencia', $bancos, Money::of('750000.00'), null,
            [new PayableAllocation($payable('FC-1'), Money::of('500000.00')), new PayableAllocation($payable('FC-2'), Money::of('250000.00'))],
            new \DateTimeImmutable('2026-10-03'), $user, new \DateTimeImmutable(),
        );

        $draft = SupplierPaymentPosting::entryFor($payment, $user);

        self::assertSame('supplier_payment', $draft->sourceType, 'The libro diario links the entry to the payment.');
        self::assertTrue($payment->id()->equals($draft->sourceId));
        self::assertSame('RP-12', $draft->sourceNumber);
        self::assertSame('2026-10-02', $draft->date->format('Y-m-d'), 'Dated the payment’s date.');
        self::assertSame('Recibo de pago RP-12 · Proveedor Uno S.A.S.', $draft->description);

        $lines = array_map(static fn (EntryLine $l) => [
            $l->side, $l->amount->toString(), $l->accountId?->toRfc4122(), $l->concept, $l->terceroId?->toRfc4122(), $l->description,
        ], $draft->lines);
        self::assertSame([
            [Side::Debit, '500000.00', null, PostingConcept::Payables, $supplier->toRfc4122(), 'FC-1'],
            [Side::Debit, '250000.00', null, PostingConcept::Payables, $supplier->toRfc4122(), 'FC-2'],
            [Side::Credit, '750000.00', $bancos->toRfc4122(), null, $supplier->toRfc4122(), null],
        ], $lines, 'Dr proveedores (tercero) once per invoice paid; Cr the method’s account for the amount.');
    }
}
