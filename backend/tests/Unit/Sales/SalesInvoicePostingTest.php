<?php

namespace App\Tests\Unit\Sales;

use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Sales\Application\Posting\SalesInvoicePosting;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * PRD Appendix A.1, the factura de venta's entry: Dr the payment-method account per contado line and 1305 Clientes
 * (tercero) per crédito line, Dr 4175 for the discount (posted gross) and Dr 1355xx for each retención suffered (§9 Q4:
 * at emission); Cr the revenue account (the product's, else the posting rule) for Total bruto and each impuesto cargo to
 * its account. Σ débitos = Total neto + Retenciones + Descuentos = Σ créditos = Total bruto + Impuestos.
 */
final class SalesInvoicePostingTest extends TestCase
{
    use BuildsInvoices;

    /**
     * @param list<EntryLine> $lines
     *
     * @return list<array{string, string, string, ?string}> side, amount, account id or concept, tercero
     */
    private static function rows(array $lines): array
    {
        return array_map(static fn (EntryLine $l) => [
            $l->side->value,
            $l->amount->toString(),
            $l->accountId?->toRfc4122() ?? $l->concept->value ?? '?',
            $l->terceroId?->toRfc4122(),
        ], $lines);
    }

    /**
     * @param list<EntryLine> $lines
     */
    private static function total(array $lines, Side $side): string
    {
        return Money::sum(...array_map(static fn (EntryLine $l) => $l->amount, array_values(array_filter($lines, static fn (EntryLine $l) => $l->side === $side))))->toString();
    }

    public function testTheEntryFollowsAppendixA1(): void
    {
        $productWithAccount = Uuid::v7();
        $revenue = Uuid::v7();
        $ivaAccount = Uuid::v7();
        $caja = Uuid::v7();
        $invoice = self::draft('2026-10-01');
        $invoice->replaceLines([
            // 2 × 1.000.000, 10 % off, IVA 19 % (to the tax's account), ReteFuente 4 % (no account: its concept).
            self::line('2', '1000000', '10', self::iva('19.0000', $ivaAccount), self::withholding('retefuente', '4.0000'), $productWithAccount),
            // 500.000 of a product without a revenue account, Impoconsumo 8 % and ReteICA 1 %, both by concept.
            self::line('1', '500000', '0', new TaxSnapshot(Uuid::v7(), 'Impoconsumo 8 %', 'impoconsumo', TaxCalculation::Percentage, '8.0000', null), self::withholding('reteica', '1.0000')),
        ]);
        $invoice->replacePayments([self::cash('1000000', $caja), self::credit('1605000')]);
        self::emit($invoice);

        $lines = SalesInvoicePosting::linesFor($invoice, [$productWithAccount->toRfc4122() => $revenue]);

        $client = self::client()->toRfc4122();
        self::assertSame([
            ['debit', '1000000.00', $caja->toRfc4122(), null],
            ['debit', '1605000.00', 'clientes', $client],
            ['debit', '200000.00', 'descuento_ventas', null],
            ['debit', '72000.00', 'retefuente_sufrida', $client],
            ['debit', '5000.00', 'reteica_sufrida', $client],
            ['credit', '2000000.00', $revenue->toRfc4122(), null],
            ['credit', '500000.00', 'ingreso', null],
            ['credit', '342000.00', $ivaAccount->toRfc4122(), null],
            ['credit', '40000.00', 'impoconsumo', null],
        ], self::rows($lines));

        // Σ débitos = Total neto + Retenciones + Descuentos; Σ créditos = Total bruto + Impuestos.
        self::assertSame('2882000.00', $invoice->netTotal()->plus($invoice->withholdingTotal())->plus($invoice->discountTotal())->toString());
        self::assertSame('2882000.00', $invoice->grossTotal()->plus($invoice->taxTotal())->toString());
        self::assertSame('2882000.00', self::total($lines, Side::Debit));
        self::assertSame('2882000.00', self::total($lines, Side::Credit));
    }

    public function testLinesPostingToTheSameAccountAreGrouped(): void
    {
        $product = Uuid::v7();
        $revenue = Uuid::v7();
        $ivaAccount = Uuid::v7();
        $invoice = self::draft();
        $invoice->replaceLines([
            self::line('1', '0.3333', '0', self::iva('19.0000', $ivaAccount), null, $product),
            self::line('1', '0.3333', '0', self::iva('19.0000', $ivaAccount), null, $product),
            self::line('1', '0.3333', '0', self::iva('19.0000', $ivaAccount), null, $product),
        ]);
        $invoice->replacePayments([self::credit('1.19')]);
        self::emit($invoice);

        $lines = SalesInvoicePosting::linesFor($invoice, [$product->toRfc4122() => $revenue]);

        self::assertSame([
            ['debit', '1.19', 'clientes', self::client()->toRfc4122()],
            ['credit', '1.00', $revenue->toRfc4122(), null],
            ['credit', '0.19', $ivaAccount->toRfc4122(), null],
        ], self::rows($lines), 'One movement per account, and the line shares add up to the rounded totals: balanced to the cent.');
    }

    public function testATaxWithoutAnAccountPostsToItsKindsConcept(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([
            self::line('1', '100', '0', self::iva('19.0000'), self::withholding('reteiva', '15.0000')),
        ]);
        $invoice->replacePayments([self::credit('104')]);
        self::emit($invoice);

        $rows = self::rows(SalesInvoicePosting::linesFor($invoice, []));

        self::assertContains(['credit', '19.00', 'iva_generado', null], $rows, 'IVA → 2408 IVA generado.');
        self::assertContains(['debit', '15.00', 'reteiva_sufrida', self::client()->toRfc4122()], $rows, 'ReteIVA → 135517.');
        self::assertContains(['credit', '100.00', 'ingreso', null], $rows, 'No product account: the posting rule ingreso.');
    }

    public function testAWithholdingWithItsOwnAccountPostsThere(): void
    {
        // A ReteICA of a municipality, under 135518 (A.10).
        $municipal = Uuid::v7();
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '1000', '0', null, self::withholding('reteica', '0.9660', $municipal))]);
        $invoice->replacePayments([self::credit('990.34')]);
        self::emit($invoice);

        self::assertContains(['debit', '9.66', $municipal->toRfc4122(), self::client()->toRfc4122()], self::rows(SalesInvoicePosting::linesFor($invoice, [])));
    }

    public function testNothingIsPostedForAZeroAmount(): void
    {
        $invoice = self::draft();
        $invoice->replaceLines([self::line('1', '100', '0', self::iva('0.0000'))]);
        $invoice->replacePayments([self::cash('100')]);
        self::emit($invoice);

        $rows = self::rows(SalesInvoicePosting::linesFor($invoice, []));

        self::assertCount(2, $rows, 'No 0 discount, no 0 IVA: only caja and ingreso.');
    }

    public function testTheDraftNamesTheInvoice(): void
    {
        $invoice = self::draft('2026-10-01');
        $invoice->replaceLines([self::line('1', '100')]);
        $invoice->replacePayments([self::cash('100')]);
        self::emit($invoice, number: 12);

        $draft = SalesInvoicePosting::entryFor($invoice, [], self::user());

        self::assertTrue(self::company()->equals($draft->companyId));
        self::assertSame('2026-10-01', $draft->date->format('Y-m-d'), 'Posted on the invoice date.');
        self::assertSame('sales_invoice', $draft->sourceType);
        self::assertTrue($invoice->id()->equals($draft->sourceId));
        self::assertSame('FE-12', $draft->sourceNumber);
        self::assertSame('Factura de venta FE-12 · Cliente Uno S.A.S.', $draft->description);
        self::assertTrue(self::user()->equals($draft->userId));
        self::assertCount(2, $draft->lines);
    }
}
