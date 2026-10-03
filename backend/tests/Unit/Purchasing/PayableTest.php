<?php

namespace App\Tests\Unit\Purchasing;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\PayableVoided;
use App\Purchasing\Domain\Model\Payable;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A payable (cartera de proveedores) is paid down by supplier payments (§4.11), opened again when a payment is voided,
 * and stops being owed when its invoice is voided.
 */
final class PayableTest extends TestCase
{
    private static function payable(string $amount = '500000.00'): Payable
    {
        return new Payable(Uuid::v7(), Uuid::v7(), 'FC-1', Uuid::v7(), new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31'), Money::of($amount));
    }

    public function testPaymentsReduceTheBalanceAndAVoidedPaymentRestoresIt(): void
    {
        $payable = self::payable();

        $payable->allocate(Money::of('200000.00'));
        self::assertSame('300000.00', $payable->balance()->toString());
        self::assertTrue($payable->isOpen());

        $payable->allocate(Money::of('300000.00'));
        self::assertSame('0.00', $payable->balance()->toString());
        self::assertFalse($payable->isOpen(), 'Paid in full, it leaves cartera.');

        $payable->release(Money::of('300000.00'));
        self::assertSame('300000.00', $payable->balance()->toString(), 'A voided payment is owed again.');
    }

    public function testAPaymentCannotExceedTheBalance(): void
    {
        $payable = self::payable();
        $payable->allocate(Money::of('400000.00'));

        $this->expectException(AllocationExceedsBalance::class);
        $payable->allocate(Money::of('100000.01'));
    }

    public function testReleasingCannotOweMoreThanTheInvoiceSaid(): void
    {
        $payable = self::payable();

        $this->expectException(AllocationExceedsBalance::class);
        $payable->release(Money::of('0.01'));
    }

    public function testAnAmountMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::payable()->allocate(Money::zero());
    }

    public function testAVoidedPayableIsNotOwedAndCannotBePaid(): void
    {
        $payable = self::payable();
        $payable->void();

        self::assertTrue($payable->isVoided());
        self::assertFalse($payable->isOpen());

        $this->expectException(PayableVoided::class);
        $payable->allocate(Money::of('1.00'));
    }
}
