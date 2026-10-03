<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Application\Payables\PayableAllocations;
use App\Purchasing\Application\Query\PayableQueries;
use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\PayableNotFound;
use App\Purchasing\Infrastructure\Persistence\DoctrinePayableRepository;
use App\Purchasing\Infrastructure\Persistence\DoctrinePurchaseInvoiceRepository;
use App\Purchasing\Infrastructure\Query\DoctrinePayableQueries;
use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What the supplier payment (item 12) calls inside its own transaction: apply an amount to a payable, or take it back
 * when the payment is voided; the invoice follows (partially_paid, paid, emitted again).
 */
final class PayableAllocationsTest extends ApiTestCase
{
    use PurchasingFixtures;

    private function allocations(): PayableAllocations
    {
        // Private services the container does not hand to tests: assembled from their real parts.
        return new PayableAllocations(new DoctrinePayableRepository($this->em()), new DoctrinePurchaseInvoiceRepository($this->em()));
    }

    private function payableQueries(): PayableQueries
    {
        return new DoctrinePayableQueries($this->em());
    }

    private function payableOf(string $invoiceId): Uuid
    {
        $open = $this->payableQueries()->openFor($this->company, $this->supplier);
        foreach ($open as $payable) {
            if ($payable->invoiceId === $invoiceId) {
                return Uuid::fromString($payable->id);
            }
        }
        throw new \LogicException('No open payable.');
    }

    public function testApplyingAndUnapplyingMovesTheBalanceAndTheInvoiceStatus(): void
    {
        $this->startPurchasing();
        $invoice = $this->emitInvoice();
        $payable = $this->payableOf($invoice['id']);

        $this->inTransaction(fn () => $this->allocations()->apply($this->company, $payable, Money::of('150000.00')));
        $read = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame('partially_paid', $read['status']);
        self::assertSame('150000.00', $read['paid_amount']);
        self::assertSame('1000000.00', $read['payables'][0]['balance']);

        $this->inTransaction(fn () => $this->allocations()->apply($this->company, $payable, Money::of('1000000.00')));
        self::assertSame('paid', $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}")['status']);
        self::assertSame([], $this->payableQueries()->openFor($this->company, $this->supplier), 'A paid payable leaves cartera.');

        $this->inTransaction(fn () => $this->allocations()->unapply($this->company, $payable, Money::of('1150000.00')));
        $read = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame('emitted', $read['status'], 'A voided payment opens the invoice again.');
        self::assertSame('1150000.00', $read['balance']);
    }

    public function testMoreThanTheBalanceIsRefused(): void
    {
        $this->startPurchasing();
        $invoice = $this->emitInvoice();

        $this->expectException(AllocationExceedsBalance::class);
        $this->inTransaction(fn () => $this->allocations()->apply($this->company, $this->payableOf($invoice['id']), Money::of('1150000.01')));
    }

    public function testAnotherCompanysPayableIsNotFound(): void
    {
        $this->startPurchasing();
        $payable = $this->payableOf($this->emitInvoice()['id']);
        $this->signOut();
        $other = $this->startCompany('bea@otra.co', '900765432', 'Otra S.A.S.');

        $this->expectException(PayableNotFound::class);
        $this->inTransaction(fn () => $this->allocations()->apply($other, $payable, Money::of('1.00')));
    }
}
