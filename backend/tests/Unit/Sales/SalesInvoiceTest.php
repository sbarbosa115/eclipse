<?php

namespace App\Tests\Unit\Sales;

use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Sales\Domain\Error\DocumentHasAllocations;
use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Error\DocumentNotEmitted;
use App\Sales\Domain\Error\PaymentsDoNotMatchTotal;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Model\InvoiceStatus;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The factura de venta's life (§4.6, §4.8, §4.12): a draft is edited freely, emission checks it, numbers it, freezes it
 * and opens a receivable per crédito line; a void keeps the number; receipts pay it down.
 */
final class SalesInvoiceTest extends TestCase
{
    use BuildsInvoices;

    /**
     * @return list<string> the violated fields
     */
    private static function violations(callable $act): array
    {
        try {
            $act();
        } catch (InvalidValues $e) {
            return array_map(static fn (array $v) => $v['field'], $e->violations());
        }
        self::fail('Expected the invoice to be refused.');
    }

    public function testADraftHasNoNumberAndItsTotalsFollowItsLines(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('2', '1000000', '10', self::iva(), self::withholding('retefuente', '4.0000'))]);

        self::assertSame(InvoiceStatus::Draft, $invoice->status());
        self::assertNull($invoice->number(), 'A draft takes its number only at emission (§4.8).');
        self::assertSame('2000000.00', $invoice->grossTotal()->toString());
        self::assertSame('200000.00', $invoice->discountTotal()->toString());
        self::assertSame('342000.00', $invoice->taxTotal()->toString());
        self::assertSame('72000.00', $invoice->withholdingTotal()->toString());
        self::assertSame('2070000.00', $invoice->netTotal()->toString(), 'Total neto = subtotal + impuestos − retenciones.');
        self::assertSame('2070000.00', $invoice->lines()[0]->totalAmount()->toString(), 'Each line carries its share of the totals.');
        self::assertSame(1, $invoice->lines()[0]->position());
    }

    public function testReplacingTheLinesReplacesThemAll(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '100'), self::line('1', '200')]);
        $invoice->replaceLines([self::line('3', '50')]);

        self::assertCount(1, $invoice->lines());
        self::assertSame('150.00', $invoice->netTotal()->toString());
    }

    public function testAHeaderChangeIsKeptOnTheDraft(): void
    {
        $invoice = self::draft();
        $other = Uuid::v7();
        $contact = Uuid::v7();
        $invoice->revise($other, 'Otro Cliente', $contact, null, new \DateTimeImmutable('2026-09-30'), '  Entregar en bodega  ');

        self::assertTrue($other->equals($invoice->terceroId()));
        self::assertSame('Otro Cliente', $invoice->terceroName());
        self::assertTrue($contact->equals($invoice->contactId() ?? Uuid::v7()));
        self::assertSame('2026-09-30', $invoice->issueDate()->format('Y-m-d'));
        self::assertSame('Entregar en bodega', $invoice->notes());
    }

    public function testPaymentRowsAreCheckedWhenSaved(): void
    {
        $invoice = self::draft();
        $fields = self::violations(static fn () => $invoice->replacePayments([
            self::cash('0'),
            self::credit('100', '2026-09-01'),
        ]));

        self::assertSame(['payments.0.amount', 'payments.1.due_date'], $fields, 'An amount is positive and a crédito is not due before the invoice date.');
    }

    public function testADraftSavesPaymentsThatDoNotAddUpYet(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::cash('400')]);

        self::assertCount(1, $invoice->payments(), '§4.6: the formas de pago must match only to emit.');
        self::assertSame(1, $invoice->payments()[0]->position());
    }

    public function testEmissionNumbersAndFreezesTheInvoice(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::cash('1000')]);
        $resolution = Uuid::v7();

        $invoice->emit($resolution, 'FE', 27, 105, new \DateTimeImmutable('2026-10-03'), self::user(), new \DateTimeImmutable('2026-10-03 10:00'));

        self::assertSame('FE-27', $invoice->number(), 'Printed: the resolution prefix and the authorised number.');
        self::assertSame(27, $invoice->authorisedNumber());
        self::assertSame(105, $invoice->internalNumber(), '§4.8: the internal consecutive is stored too.');
        self::assertSame('FE', $invoice->prefix());
        self::assertTrue($resolution->equals($invoice->resolutionId() ?? Uuid::v7()));
        self::assertTrue(self::user()->equals($invoice->emittedBy() ?? Uuid::v7()), '§4.14: who emitted it.');
        self::assertSame('2026-10-03 10:00', $invoice->emittedAt()?->format('Y-m-d H:i'));

        foreach ([
            static fn () => $invoice->replaceLines([self::line('1', '5')]),
            static fn () => $invoice->replacePayments([]),
            static fn () => $invoice->revise(self::client(), 'X', null, null, new \DateTimeImmutable('2026-10-01'), null),
            static fn () => self::emit($invoice),
        ] as $change) {
            try {
                $change();
                self::fail('An emitted invoice is immutable (§4.6).');
            } catch (DocumentNotDraft $e) {
                self::assertSame('document_not_draft', $e->errorCode());
            }
        }
    }

    public function testEmissionNeedsALine(): void
    {
        $invoice = self::draft();

        self::assertSame(['lines'], self::violations(static fn () => self::emit($invoice)));
    }

    public function testEmissionNeedsThePaymentsToAddUpToTotalNeto(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1190')]);
        $invoice->replacePayments([self::cash('1000')]);

        try {
            self::emit($invoice);
            self::fail('Σ formas de pago must equal total neto (§4.6).');
        } catch (PaymentsDoNotMatchTotal $e) {
            self::assertSame('payments_do_not_match_total', $e->errorCode());
            self::assertSame(['payments_total' => '1000.00', 'net_total' => '1190.00'], $e->details());
        }
        self::assertSame(InvoiceStatus::Draft, $invoice->status(), 'Nothing changed.');
    }

    public function testAnInvoiceIsNotEmittedWithADateInTheFuture(): void
    {
        $invoice = self::draft('2026-10-04');
        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::cash('10')]);

        self::assertSame(['issue_date'], self::violations(static fn () => self::emit($invoice, today: '2026-10-03')), '§9 Q11: backdating is allowed, post-dating is not.');
    }

    public function testABackdatedInvoiceIsEmitted(): void
    {
        $invoice = self::draft('2026-09-15');
        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::credit('10', '2026-09-30')]);

        self::emit($invoice, today: '2026-10-03');

        self::assertSame(InvoiceStatus::Emitted, $invoice->status());
    }

    public function testACreditDueBeforeTheIssueDateStopsTheEmission(): void
    {
        // Saved with a valid due date, then the issue date moved past it.
        $invoice = self::draft('2026-09-01');
        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::credit('10', '2026-09-20')]);
        $invoice->revise(self::client(), 'Cliente', null, null, new \DateTimeImmutable('2026-09-25'), null);

        self::assertSame(['payments.0.due_date'], self::violations(static fn () => self::emit($invoice)));
    }

    public function testEmissionOpensOneReceivablePerCreditLine(): void
    {
        $invoice = self::draft('2026-10-01');
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::cash('400'), self::credit('350', '2026-10-31'), self::credit('250', '2026-11-30')]);

        $receivables = self::emit($invoice);

        self::assertCount(2, $receivables, '§9 Q9: one receivable per crédito payment line.');
        [$first, $second] = $receivables;
        self::assertSame(['350.00', '350.00', '2026-10-31'], [$first->amount()->toString(), $first->balance()->toString(), $first->dueDate()->format('Y-m-d')]);
        self::assertSame(['250.00', '2026-11-30'], [$second->amount()->toString(), $second->dueDate()->format('Y-m-d')]);
        self::assertSame('FE-1', $first->invoiceNumber());
        self::assertTrue($invoice->id()->equals($first->invoiceId()));
        self::assertTrue(self::client()->equals($first->terceroId()));
        self::assertSame('2026-10-01', $first->issueDate()->format('Y-m-d'));
        self::assertSame(InvoiceStatus::Emitted, $invoice->status());
        self::assertSame('600.00', $invoice->balance()->toString(), 'What the client still owes: the crédito part.');
    }

    public function testAnInvoicePaidInCashIsPaidWhenEmitted(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::cash('1000')]);

        self::assertSame([], self::emit($invoice), 'No crédito line, no receivable.');
        self::assertSame(InvoiceStatus::Paid, $invoice->status(), 'Nothing is owed: the invoice is paid.');
        self::assertSame('0.00', $invoice->balance()->toString());
    }

    public function testTheEntryIsRecordedOnTheInvoice(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::cash('10')]);
        self::emit($invoice);
        $entry = Uuid::v7();

        $invoice->recordPosting($entry);

        self::assertTrue($entry->equals($invoice->journalEntryId() ?? Uuid::v7()));
    }

    public function testReceiptsPayTheInvoiceDownAndBack(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::cash('400'), self::credit('600')]);
        [$receivable] = self::emit($invoice);

        $receivable->apply(Money::of('200'));
        $invoice->applyPayment(Money::of('200'));
        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->status());
        self::assertSame('200.00', $invoice->paidAmount()->toString());
        self::assertSame('400.00', $invoice->balance()->toString());
        self::assertSame('400.00', $receivable->balance()->toString());

        $receivable->apply(Money::of('400'));
        $invoice->applyPayment(Money::of('400'));
        self::assertSame(InvoiceStatus::Paid, $invoice->status(), 'AC-4: paid when the crédito part is collected.');
        self::assertFalse($receivable->isOpen());

        $receivable->unapply(Money::of('400'));
        $invoice->revertPayment(Money::of('400'));
        self::assertSame(InvoiceStatus::PartiallyPaid, $invoice->status(), 'Voiding a receipt gives the amount back.');
        $receivable->unapply(Money::of('200'));
        $invoice->revertPayment(Money::of('200'));
        self::assertSame(InvoiceStatus::Emitted, $invoice->status());
        self::assertSame('600.00', $receivable->balance()->toString());
    }

    public function testAReceiptCannotTakeMoreThanIsOwed(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::credit('1000')]);
        [$receivable] = self::emit($invoice);

        try {
            $receivable->apply(Money::of('1000.01'));
            self::fail('§9 Q16: no over-payment.');
        } catch (AllocationExceedsBalance $e) {
            self::assertSame('allocation_exceeds_balance', $e->errorCode());
        }
        $this->expectException(AllocationExceedsBalance::class);
        $invoice->applyPayment(Money::of('1000.01'));
    }

    public function testADraftCannotBePaid(): void
    {
        $invoice = self::draft();

        $this->expectException(DocumentNotEmitted::class);
        $invoice->applyPayment(Money::of('1'));
    }

    public function testVoidingKeepsTheNumberAndRecordsWhoAndWhy(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::credit('1000')]);
        [$receivable] = self::emit($invoice, number: 8);
        $voider = Uuid::v7();

        $invoice->void('  Error en el precio  ', false, $voider, new \DateTimeImmutable('2026-10-05 11:00'));
        $receivable->void();
        $reversal = Uuid::v7();
        $invoice->recordReversal($reversal);

        self::assertSame(InvoiceStatus::Voided, $invoice->status());
        self::assertSame('FE-8', $invoice->number(), '§4.12: the number is kept, never reused.');
        self::assertSame('Error en el precio', $invoice->voidReason());
        self::assertTrue($voider->equals($invoice->voidedBy() ?? Uuid::v7()));
        self::assertSame('2026-10-05 11:00', $invoice->voidedAt()?->format('Y-m-d H:i'));
        self::assertTrue($reversal->equals($invoice->reversalEntryId() ?? Uuid::v7()));
        self::assertSame('0.00', $invoice->balance()->toString(), 'A voided invoice is owed by nobody.');
        self::assertTrue($receivable->isVoided());
        self::assertFalse($receivable->isOpen(), 'It leaves the cartera.');
    }

    public function testAVoidNeedsAReason(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::cash('10')]);
        self::emit($invoice);

        self::assertSame(['reason'], self::violations(static fn () => $invoice->void('   ', false, self::user(), new \DateTimeImmutable())));
    }

    public function testAnInvoiceWithMoneyAppliedIsNotVoided(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000')]);
        $invoice->replacePayments([self::credit('1000')]);
        self::emit($invoice);

        try {
            $invoice->void('Error', true, self::user(), new \DateTimeImmutable());
            self::fail('§4.12: void the receipts first.');
        } catch (DocumentHasAllocations $e) {
            self::assertSame('document_has_allocations', $e->errorCode());
        }

        $invoice->applyPayment(Money::of('1'));
        $this->expectException(DocumentHasAllocations::class);
        $invoice->void('Error', false, self::user(), new \DateTimeImmutable());
    }

    public function testOnlyAnEmittedInvoiceIsVoided(): void
    {
        $invoice = self::draft();
        try {
            $invoice->void('Error', false, self::user(), new \DateTimeImmutable());
            self::fail('A draft is not voided.');
        } catch (DocumentNotEmitted $e) {
            self::assertSame('document_not_emitted', $e->errorCode());
        }

        $invoice->replaceLines([self::line('1', '10')]);
        $invoice->replacePayments([self::cash('10')]);
        self::emit($invoice);
        $invoice->void('Error', false, self::user(), new \DateTimeImmutable());

        $this->expectException(DocumentNotEmitted::class);
        $invoice->void('Otra vez', false, self::user(), new \DateTimeImmutable());
    }

    public function testADraftRemembersTheQuotationItCameFrom(): void
    {
        $invoice = self::draft();
        $quotation = Uuid::v7();
        $invoice->originatesFrom($quotation);

        self::assertTrue($quotation->equals($invoice->quotationId() ?? Uuid::v7()));
    }
}
