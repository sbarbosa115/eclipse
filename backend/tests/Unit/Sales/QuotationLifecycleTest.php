<?php

namespace App\Tests\Unit\Sales;

use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Error\QuotationAlreadyConverted;
use App\Sales\Domain\Error\QuotationNotOpen;
use App\Sales\Domain\Model\InvoiceLineDraft;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationStatus;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The cotización's life (§4.7, §9 Q17): a draft is edited like an invoice's (no formas de pago), emission numbers and
 * freezes it and posts nothing, an emitted one is accepted, rejected, voided or runs past its vencimiento (read as
 * `expired`), and it converts into one draft invoice, once.
 */
final class QuotationLifecycleTest extends TestCase
{
    private static function draft(string $issue = '2026-10-01', ?string $expiry = '2026-10-31'): Quotation
    {
        return new Quotation(Uuid::v7(), Uuid::v7(), 'Cliente Uno S.A.S.', new \DateTimeImmutable($issue), new \DateTimeImmutable($expiry ?? $issue), Uuid::v7(), new \DateTimeImmutable("$issue 09:00"));
    }

    private static function line(string $quantity = '1', string $price = '1000000', ?TaxSnapshot $tax = null): InvoiceLineDraft
    {
        return new InvoiceLineDraft(Uuid::v7(), 'Consultoría', Quantity::of($quantity), UnitPrice::of($price), Rate::of('0'), $tax ?? new TaxSnapshot(Uuid::v7(), 'IVA 19 %', 'iva', TaxCalculation::Percentage, '19.0000', null), TaxSnapshot::none());
    }

    private static function emitted(string $today = '2026-10-03'): Quotation
    {
        $quotation = self::draft();
        $quotation->replaceLines([self::line()]);
        $quotation->emit('C', 7, new \DateTimeImmutable($today), Uuid::v7(), new \DateTimeImmutable("$today 10:00"));

        return $quotation;
    }

    /** @return list<string> */
    private static function violations(callable $act): array
    {
        try {
            $act();
        } catch (InvalidValues $e) {
            return array_map(static fn (array $v) => $v['field'], $e->violations());
        }
        self::fail('Expected the quotation to be refused.');
    }

    public function testADraftHasLinesAndTotalsLikeAnInvoiceAndNoNumber(): void
    {
        $quotation = self::draft();
        $quotation->replaceLines([self::line('2', '1000000')]);

        self::assertSame(QuotationStatus::Draft, $quotation->status());
        self::assertNull($quotation->number());
        self::assertSame('2000000.00', $quotation->subtotal()->toString());
        self::assertSame('380000.00', $quotation->taxTotal()->toString());
        self::assertSame('2380000.00', $quotation->netTotal()->toString());
        self::assertSame('2380000.00', $quotation->lines()[0]->totalAmount()->toString());
    }

    public function testTheHeaderTheEncabezadoAndTheCondicionesAreKeptAsPlainText(): void
    {
        $quotation = self::draft();
        $employee = Uuid::v7();
        $quotation->revise(Uuid::v7(), 'Otro', null, $employee, new \DateTimeImmutable('2026-10-02'), new \DateTimeImmutable('2026-11-01'), "  Hola <b>Ana</b>\n\nGracias  ", '  50 % de anticipo  ', ' Nota ');

        self::assertSame("Hola <b>Ana</b>\n\nGracias", $quotation->header(), 'Stored as typed (trimmed): it is escaped when shown, never interpreted.');
        self::assertSame('50 % de anticipo', $quotation->terms());
        self::assertSame('Nota', $quotation->notes());
        self::assertTrue($employee->equals($quotation->responsibleId() ?? Uuid::v7()));
        self::assertSame('2026-11-01', $quotation->expiryDate()->format('Y-m-d'));
    }

    public function testTheOfferIsValidForThirtyDaysUnlessSaidOtherwise(): void
    {
        $quotation = self::draft();
        $quotation->revise(Uuid::v7(), 'Otro', null, null, new \DateTimeImmutable('2026-10-02'), null, null, null, null);

        self::assertSame('2026-11-01', $quotation->expiryDate()->format('Y-m-d'), '§9 Q17: 30 days.');
    }

    public function testTheOfferCannotExpireBeforeItIsMade(): void
    {
        $quotation = self::draft();

        self::assertSame(['expiry_date'], self::violations(static fn () => $quotation->revise(Uuid::v7(), 'X', null, null, new \DateTimeImmutable('2026-10-10'), new \DateTimeImmutable('2026-10-09'), null, null, null)));
    }

    public function testEmissionNumbersFreezesAndRecordsWhoEmittedIt(): void
    {
        $quotation = self::emitted();

        self::assertSame(QuotationStatus::Emitted, $quotation->status());
        self::assertSame('C-7', $quotation->number());
        self::assertSame('C', $quotation->prefix());
        self::assertSame(7, $quotation->sequence());
        self::assertNotNull($quotation->emittedBy());
        self::assertSame('2026-10-03 10:00', $quotation->emittedAt()?->format('Y-m-d H:i'));

        foreach ([
            static fn () => $quotation->replaceLines([self::line()]),
            static fn () => $quotation->revise(Uuid::v7(), 'X', null, null, new \DateTimeImmutable('2026-10-01'), null, null, null, null),
            static fn () => $quotation->emit('C', 8, new \DateTimeImmutable('2026-10-03'), Uuid::v7(), new \DateTimeImmutable()),
        ] as $change) {
            try {
                $change();
                self::fail('An emitted quotation is immutable.');
            } catch (DocumentNotDraft $e) {
                self::assertSame('document_not_draft', $e->errorCode());
            }
        }
    }

    public function testEmissionNeedsALineAndADateThatIsNotInTheFuture(): void
    {
        self::assertSame(['lines'], self::violations(static fn () => self::draft()->assertEmittable(new \DateTimeImmutable('2026-10-03'))));

        $future = self::draft('2026-10-05', '2026-11-05');
        $future->replaceLines([self::line()]);
        self::assertSame(['issue_date'], self::violations(static fn () => $future->assertEmittable(new \DateTimeImmutable('2026-10-03'))));
    }

    public function testAnEmittedQuotationIsAccepted(): void
    {
        $quotation = self::emitted();
        $quotation->accept();

        self::assertSame(QuotationStatus::Accepted, $quotation->status());
    }

    public function testAnEmittedQuotationIsRejected(): void
    {
        $quotation = self::emitted();
        $quotation->reject();

        self::assertSame(QuotationStatus::Rejected, $quotation->status());
    }

    public function testADraftOrADecidedQuotationIsNotAcceptedOrRejected(): void
    {
        $draft = self::draft();
        $accepted = self::emitted();
        $accepted->accept();
        $rejected = self::emitted();
        $rejected->reject();

        foreach ([
            static fn () => $draft->accept(),
            static fn () => $draft->reject(),
            static fn () => $accepted->reject(),
            static fn () => $accepted->accept(),
            static fn () => $rejected->accept(),
            static fn () => $rejected->reject(),
        ] as $change) {
            try {
                $change();
                self::fail('Only an emitted quotation not yet decided is decided.');
            } catch (QuotationNotOpen $e) {
                self::assertSame('quotation_not_open', $e->errorCode());
            }
        }
    }

    public function testAQuotationPastItsVencimientoReadsAsExpiredAndIsStillDecided(): void
    {
        $quotation = self::emitted();

        self::assertSame(QuotationStatus::Emitted, $quotation->statusOn(new \DateTimeImmutable('2026-10-31')), 'The vencimiento day itself is still valid.');
        self::assertSame(QuotationStatus::Expired, $quotation->statusOn(new \DateTimeImmutable('2026-11-01')));
        self::assertSame(QuotationStatus::Emitted, $quotation->status(), 'Nothing is written: it is computed on read.');

        $quotation->accept();
        self::assertSame(QuotationStatus::Accepted, $quotation->statusOn(new \DateTimeImmutable('2026-11-01')), 'A client may accept late (decided 2026-10-04).');

        $rejected = self::emitted();
        $rejected->reject();
        self::assertSame(QuotationStatus::Rejected, $rejected->status());
    }

    public function testDecidedAndDraftQuotationsNeverReadAsExpired(): void
    {
        $late = new \DateTimeImmutable('2027-01-01');
        $draft = self::draft();
        $accepted = self::emitted();
        $accepted->accept();
        $voided = self::emitted();
        $voided->void('Error', Uuid::v7(), new \DateTimeImmutable());

        self::assertSame(QuotationStatus::Draft, $draft->statusOn($late));
        self::assertSame(QuotationStatus::Accepted, $accepted->statusOn($late));
        self::assertSame(QuotationStatus::Voided, $voided->statusOn($late));
    }

    public function testAnEmittedQuotationIsVoidedWithAReasonAndKeepsItsNumber(): void
    {
        $quotation = self::emitted();
        $by = Uuid::v7();
        $quotation->void('  El cliente cambió el alcance  ', $by, new \DateTimeImmutable('2026-10-05 08:00'));

        self::assertSame(QuotationStatus::Voided, $quotation->status());
        self::assertSame('C-7', $quotation->number());
        self::assertSame('El cliente cambió el alcance', $quotation->voidReason());
        self::assertTrue($by->equals($quotation->voidedBy() ?? Uuid::v7()));
    }

    public function testAVoidNeedsAReasonAndAnEmittedQuotation(): void
    {
        self::assertSame(['reason'], self::violations(static fn () => self::emitted()->void('  ', Uuid::v7(), new \DateTimeImmutable())));

        foreach ([self::draft(), self::emitted()] as $i => $quotation) {
            if (1 === $i) {
                $quotation->void('Una vez', Uuid::v7(), new \DateTimeImmutable());
            }
            try {
                $quotation->void('Otra vez', Uuid::v7(), new \DateTimeImmutable());
                self::fail('Only an emitted quotation is voided, once.');
            } catch (QuotationNotOpen $e) {
                self::assertSame('quotation_not_open', $e->errorCode());
            }
        }
    }

    public function testAnAcceptedQuotationIsNotVoided(): void
    {
        $quotation = self::emitted();
        $quotation->accept();

        $this->expectException(QuotationNotOpen::class);
        $quotation->void('Tarde', Uuid::v7(), new \DateTimeImmutable());
    }

    public function testConvertingAcceptsTheQuotationAndKeepsTheInvoice(): void
    {
        $quotation = self::emitted();
        $invoice = Uuid::v7();
        $quotation->convertedTo($invoice);

        self::assertSame(QuotationStatus::Accepted, $quotation->status());
        self::assertTrue($invoice->equals($quotation->convertedInvoiceId() ?? Uuid::v7()));
    }

    public function testAQuotationConvertsOnce(): void
    {
        $quotation = self::emitted();
        $quotation->convertedTo(Uuid::v7());

        try {
            $quotation->assertConvertible();
            self::fail('§9 Q17: convert once.');
        } catch (QuotationAlreadyConverted $e) {
            self::assertSame('quotation_already_converted', $e->errorCode());
        }
        $this->expectException(QuotationAlreadyConverted::class);
        $quotation->convertedTo(Uuid::v7());
    }

    public function testAnAcceptedQuotationNotYetConvertedConvertsLater(): void
    {
        $quotation = self::emitted();
        $quotation->accept();
        $quotation->assertConvertible();
        $quotation->convertedTo(Uuid::v7());

        self::assertNotNull($quotation->convertedInvoiceId());
    }

    public function testOnlyAnEmittedQuotationConvertsEvenAfterItsVencimiento(): void
    {
        $rejected = self::emitted();
        $rejected->reject();
        $voided = self::emitted();
        $voided->void('x', Uuid::v7(), new \DateTimeImmutable());

        self::emitted()->assertConvertible();

        foreach ([self::draft(), $rejected, $voided] as $quotation) {
            try {
                $quotation->assertConvertible();
                self::fail('Not convertible.');
            } catch (QuotationNotOpen) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
