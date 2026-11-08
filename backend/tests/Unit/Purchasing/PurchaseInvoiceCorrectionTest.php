<?php

namespace App\Tests\Unit\Purchasing;

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
 * Corregir (observations PRD §4.6): a voided invoice opens one editable copy of itself — what was recorded, not what
 * a duplicate would start from — and the two point at each other.
 */
final class PurchaseInvoiceCorrectionTest extends TestCase
{
    private Uuid $user;

    protected function setUp(): void
    {
        $this->user = Uuid::v7();
    }

    private static function date(string $ymd): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymd);
    }

    /** Emitted on 2026-10-02 (issued 10-01, due 10-31), with ReteFuente 4 % and ReteIVA 15 % on its line. */
    private function emitted(): PurchaseInvoice
    {
        $invoice = new PurchaseInvoice(Uuid::v7(), Uuid::v7(), 'Servicios Andinos S.A.S.', 'FAC-881', self::date('2026-10-01'), $this->user, self::date('2026-10-01 09:00'));
        $line = new PurchaseLineDraft(
            null,
            Uuid::v7(),
            'Mantenimiento de equipos',
            Quantity::of('1'),
            UnitPrice::of('1000000'),
            Rate::zero(),
            new TaxSnapshot(Uuid::v7(), 'IVA 19 %', 'iva', TaxCalculation::Percentage, '19.0000', null),
            [
                new TaxSnapshot(Uuid::v7(), 'ReteFuente servicios 4 %', 'retefuente', TaxCalculation::Percentage, '4.0000', null),
                new TaxSnapshot(Uuid::v7(), 'ReteIVA 15 %', 'reteiva', TaxCalculation::Percentage, '15.0000', null),
            ],
        );
        $invoice->revise($invoice->terceroId(), 'Servicios Andinos S.A.S.', 'FAC-881', self::date('2026-10-01'), self::date('2026-10-31'), 'Octubre', [$line], [
            new PurchasePaymentDraft(Uuid::v7(), 'Crédito', PaymentKind::Credit, null, Money::of('1121500.00'), self::date('2026-10-20')),
        ]);
        $invoice->emit('FC', 7, $this->user, self::date('2026-10-02 10:00'), self::date('2026-10-02'));

        return $invoice;
    }

    private function voided(): PurchaseInvoice
    {
        $invoice = $this->emitted();
        $invoice->void('Corregida: valor mal digitado', $this->user, self::date('2026-10-12 10:00'), Uuid::v7());

        return $invoice;
    }

    public function testTheCopyKeepsWhatWasRecorded(): void
    {
        $invoice = $this->voided();

        $copy = $invoice->correction($this->user, self::date('2026-10-12 10:00'));

        self::assertSame(InvoiceStatus::Draft, $copy->status());
        self::assertFalse($copy->id()->equals($invoice->id()));
        self::assertNull($copy->number(), 'Its own internal number comes at emission.');
        self::assertSame('FAC-881', $copy->supplierInvoiceNumber(), 'Unlike a duplicate, the supplier\'s number is the same invoice\'s.');
        self::assertSame(['2026-10-01', '2026-10-31'], [$copy->issueDate()->format('Y-m-d'), $copy->dueDate()?->format('Y-m-d')], 'Unlike a duplicate, the dates are not moved to today.');
        self::assertSame('2026-10-20', $copy->payments()[0]->dueDate()?->format('Y-m-d'), 'The formas de pago as they were.');
        self::assertSame('Octubre', $copy->notes());
        self::assertTrue($invoice->terceroId()->equals($copy->terceroId()));
        self::assertSame(['ReteFuente servicios 4 %', 'ReteIVA 15 %'], array_map(static fn (TaxSnapshot $t) => $t->name(), $copy->lines()[0]->withholdingTaxes()), 'Every retención.');
        self::assertSame(['1121500.00', '68500.00'], [$copy->netTotal()->toString(), $copy->withholdingTotal()->toString()]);
    }

    public function testTheTwoPointAtEachOther(): void
    {
        $invoice = $this->voided();

        $copy = $invoice->correction($this->user, self::date('2026-10-12 10:00'));

        self::assertTrue($invoice->id()->equals($copy->correctedFromId() ?? Uuid::v7()), 'The draft records what it corrects.');
        self::assertTrue($copy->id()->equals($invoice->correctedById() ?? Uuid::v7()), 'The voided invoice records what corrects it.');
        self::assertNull($invoice->correctedFromId());
        self::assertNull($copy->correctedById());
    }

    public function testOnlyAVoidedInvoiceNotYetCorrectedIsCorrected(): void
    {
        $invoice = $this->voided();
        $invoice->correction($this->user, self::date('2026-10-12 10:00'));

        $this->expectException(\LogicException::class);
        $invoice->correction($this->user, self::date('2026-10-12 10:00'));
    }

    public function testAnEmittedInvoiceIsVoidedBeforeItIsCorrected(): void
    {
        $this->expectException(\LogicException::class);
        $this->emitted()->correction($this->user, self::date('2026-10-12 10:00'));
    }

    public function testDiscardingTheCopyForgetsTheLink(): void
    {
        $invoice = $this->voided();
        $copy = $invoice->correction($this->user, self::date('2026-10-12 10:00'));

        $invoice->correctionDiscarded(Uuid::v7());
        self::assertNotNull($invoice->correctedById(), 'Another draft is not its correction.');

        $invoice->correctionDiscarded($copy->id());
        self::assertNull($invoice->correctedById(), 'The draft that corrected it is gone.');
    }
}
