<?php

namespace App\Tests\Unit\Purchasing;

use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Error\PurchaseOrderAlreadyConverted;
use App\Purchasing\Domain\Error\PurchaseOrderNotOpen;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchaseOrder;
use App\Purchasing\Domain\Model\PurchaseOrderStatus;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The orden de compra's life (observations PRD §4.8): a draft is edited like a factura de compra's (no formas de pago),
 * emission numbers and freezes it and posts nothing, an emitted one is voided or converted into a draft purchase
 * invoice, once.
 */
final class PurchaseOrderLifecycleTest extends TestCase
{
    private static function draft(string $issue = '2026-11-01'): PurchaseOrder
    {
        return new PurchaseOrder(Uuid::v7(), Uuid::v7(), 'Servicios Andinos S.A.S.', new \DateTimeImmutable($issue), Uuid::v7(), new \DateTimeImmutable("$issue 09:00"));
    }

    private static function line(string $quantity = '1', string $price = '1000000', ?string $code = null): PurchaseLineDraft
    {
        $iva = new TaxSnapshot(Uuid::v7(), 'IVA 19 %', 'iva', TaxCalculation::Percentage, '19.0000', null);

        return new PurchaseLineDraft(null === $code ? null : Uuid::v7(), null === $code ? Uuid::v7() : null, 'Resmas', Quantity::of($quantity), UnitPrice::of($price), Rate::of('0'), $iva, [], $code);
    }

    private static function emitted(): PurchaseOrder
    {
        $order = self::draft();
        $order->replaceLines([self::line()]);
        $order->emit('OC', 4, new \DateTimeImmutable('2026-11-03'), Uuid::v7(), new \DateTimeImmutable('2026-11-03 10:00'));

        return $order;
    }

    public function testADraftHasLinesAndTotalsLikeAPurchaseInvoiceAndNoNumber(): void
    {
        $order = self::draft();
        $order->replaceLines([self::line('2', '1000000', 'RES-01')]);

        self::assertSame(PurchaseOrderStatus::Draft, $order->status());
        self::assertNull($order->number());
        self::assertSame('2000000.00', $order->subtotal()->toString());
        self::assertSame('380000.00', $order->taxTotal()->toString());
        self::assertSame('2380000.00', $order->netTotal()->toString());
        self::assertSame('RES-01', $order->lines()[0]->productCode(), 'The product code travels with the line (item 5).');
    }

    public function testTheHeaderKeepsTheExpectedDateAndTheNotes(): void
    {
        $order = self::draft();
        $supplier = Uuid::v7();
        $order->revise($supplier, 'Otro S.A.S.', new \DateTimeImmutable('2026-11-02'), new \DateTimeImmutable('2026-11-20'), '  Entregar en bodega  ');

        self::assertTrue($supplier->equals($order->terceroId()));
        self::assertSame(['Otro S.A.S.', '2026-11-02', '2026-11-20', 'Entregar en bodega'], [$order->terceroName(), $order->issueDate()->format('Y-m-d'), $order->expectedDate()?->format('Y-m-d'), $order->notes()]);

        $order->revise($supplier, 'Otro S.A.S.', new \DateTimeImmutable('2026-11-02'), null, '   ');
        self::assertNull($order->expectedDate());
        self::assertNull($order->notes());
    }

    public function testTheExpectedDateCannotBeBeforeTheOrder(): void
    {
        $order = self::draft();

        try {
            $order->revise(Uuid::v7(), 'X', new \DateTimeImmutable('2026-11-10'), new \DateTimeImmutable('2026-11-09'), null);
            self::fail('Expected the order to be refused.');
        } catch (InvalidValues $e) {
            self::assertSame('expected_date', $e->violations()[0]['field']);
        }
    }

    public function testEmissionNumbersFreezesAndRecordsWhoEmittedIt(): void
    {
        $order = self::emitted();

        self::assertSame(PurchaseOrderStatus::Emitted, $order->status());
        self::assertSame(['OC-4', 'OC', 4], [$order->number(), $order->prefix(), $order->sequence()]);
        self::assertNotNull($order->emittedBy());
        self::assertSame('2026-11-03 10:00', $order->emittedAt()?->format('Y-m-d H:i'));

        foreach ([
            static fn () => $order->replaceLines([self::line()]),
            static fn () => $order->revise(Uuid::v7(), 'X', new \DateTimeImmutable('2026-11-01'), null, null),
            static fn () => $order->emit('OC', 5, new \DateTimeImmutable('2026-11-03'), Uuid::v7(), new \DateTimeImmutable()),
        ] as $change) {
            try {
                $change();
                self::fail('An emitted order is immutable.');
            } catch (DocumentNotDraft $e) {
                self::assertSame('document_not_draft', $e->errorCode());
            }
        }
    }

    public function testEmissionNeedsALineAndADateThatIsNotInTheFuture(): void
    {
        try {
            self::draft()->assertEmittable(new \DateTimeImmutable('2026-11-03'));
            self::fail('A line is needed.');
        } catch (InvalidValues $e) {
            self::assertSame(['lines'], array_column($e->violations(), 'field'));
        }

        $future = self::draft('2026-11-05');
        $future->replaceLines([self::line()]);
        try {
            $future->assertEmittable(new \DateTimeImmutable('2026-11-03'));
            self::fail('A future date is refused.');
        } catch (InvalidValues $e) {
            self::assertSame(['issue_date'], array_column($e->violations(), 'field'));
        }
    }

    public function testAnEmittedOrderIsVoidedWithAReasonAndKeepsItsNumber(): void
    {
        $order = self::emitted();
        $by = Uuid::v7();
        $order->void('  El proveedor ya no vende  ', $by, new \DateTimeImmutable('2026-11-05 08:00'));

        self::assertSame(PurchaseOrderStatus::Voided, $order->status());
        self::assertSame('OC-4', $order->number());
        self::assertSame('El proveedor ya no vende', $order->voidReason());
        self::assertTrue($by->equals($order->voidedBy() ?? Uuid::v7()));
    }

    public function testAVoidNeedsAReasonAndAnEmittedOrder(): void
    {
        try {
            self::emitted()->void('  ', Uuid::v7(), new \DateTimeImmutable());
            self::fail('A reason is needed.');
        } catch (InvalidValues $e) {
            self::assertSame('reason', $e->violations()[0]['field']);
        }

        $voided = self::emitted();
        $voided->void('Una vez', Uuid::v7(), new \DateTimeImmutable());
        $converted = self::emitted();
        $converted->convertedTo(Uuid::v7());
        foreach ([self::draft(), $voided, $converted] as $order) {
            try {
                $order->void('Otra vez', Uuid::v7(), new \DateTimeImmutable());
                self::fail('Only an emitted order not converted is voided.');
            } catch (PurchaseOrderNotOpen $e) {
                self::assertSame('purchase_order_not_open', $e->errorCode());
            }
        }
    }

    public function testConvertingKeepsTheInvoiceAndTheOrderBecomesConverted(): void
    {
        $order = self::emitted();
        $invoice = Uuid::v7();
        $order->convertedTo($invoice);

        self::assertSame(PurchaseOrderStatus::Converted, $order->status());
        self::assertTrue($invoice->equals($order->convertedInvoiceId() ?? Uuid::v7()));
    }

    public function testAnOrderConvertsOnce(): void
    {
        $order = self::emitted();
        $order->convertedTo(Uuid::v7());

        try {
            $order->assertConvertible();
            self::fail('Convert once.');
        } catch (PurchaseOrderAlreadyConverted $e) {
            self::assertSame('purchase_order_already_converted', $e->errorCode());
        }
    }

    public function testOnlyAnEmittedOrderConverts(): void
    {
        $voided = self::emitted();
        $voided->void('x', Uuid::v7(), new \DateTimeImmutable());
        foreach ([self::draft(), $voided] as $order) {
            try {
                $order->assertConvertible();
                self::fail('Only an emitted order converts.');
            } catch (PurchaseOrderNotOpen $e) {
                self::assertSame('purchase_order_not_open', $e->errorCode());
            }
        }
    }

    public function testAnInvoiceRemembersTheOrderItCameFrom(): void
    {
        $invoice = new PurchaseInvoice(Uuid::v7(), Uuid::v7(), 'Proveedor', null, new \DateTimeImmutable('2026-11-03'), Uuid::v7(), new \DateTimeImmutable());
        $order = Uuid::v7();
        $invoice->convertedFromOrder($order);

        self::assertTrue($order->equals($invoice->purchaseOrderId() ?? Uuid::v7()));
    }
}
