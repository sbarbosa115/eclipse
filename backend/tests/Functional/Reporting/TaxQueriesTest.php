<?php

namespace App\Tests\Functional\Reporting;

use App\Reporting\Application\Tax\MonthRange;
use App\Reporting\Application\Tax\TaxReports;
use App\Reporting\Application\Tax\WithholdingGroup;
use App\Reporting\Application\Tax\WithholdingKind;
use App\Reporting\Application\Tax\WithholdingLine;
use App\Reporting\Application\Tax\WithholdingReport;
use App\Reporting\Application\Tax\WithholdingSide;
use App\Tests\Support\ApiTestCase;

/**
 * The tax working papers read from the documents (observations PRD §4.7 a–c): grouped by month then kind, voided
 * invoices left out and counted, and the books' figure (from the ledger's own query service) next to each total.
 */
final class TaxQueriesTest extends ApiTestCase
{
    use TaxReportFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
    }

    private function reports(): TaxReports
    {
        return static::getContainer()->get(TaxReports::class);
    }

    private function report(WithholdingSide $side, int $from = 0, int $to = 0, ?WithholdingKind $kind = null): WithholdingReport
    {
        return $this->reports()->withholdings($this->company, $side, MonthRange::of(self::month($from), self::month($to), new \DateTimeImmutable()), $kind);
    }

    /** @return list<array{string, string, string, string, string, string}> month, kind, base, amount, books, difference */
    private static function groupsOf(WithholdingReport $report): array
    {
        return array_map(static fn (WithholdingGroup $g) => [(string) $g->month, $g->kind->value, $g->totalBase->toString(), $g->totalAmount->toString(), $g->ledgerAmount->toString(), $g->difference()->toString()], $report->groups);
    }

    public function testRetencionesPracticadasAreGroupedByMonthThenKindAndMatchTheBooks(): void
    {
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::monthStart(-1));
        $this->purchaseWith([$this->retefuente(), $this->reteIca(), $this->reteIva()], '1126840.00', self::today());

        $report = $this->report(WithholdingSide::Practiced, -1, 0);

        self::assertSame([
            [self::month(-1), 'retefuente', '1000000.00', '25000.00', '25000.00', '0.00'],
            [self::month(), 'retefuente', '1000000.00', '25000.00', '25000.00', '0.00'],
            [self::month(), 'reteiva', '190000.00', '28500.00', '28500.00', '0.00'],
            [self::month(), 'reteica', '1000000.00', '9660.00', '9660.00', '0.00'],
        ], self::groupsOf($report), 'Month first, then retefuente, reteiva, reteica; ReteIVA on the IVA; each equal to what its accounts (2365/2367/2368) moved.');
        self::assertSame([
            ['retefuente', '2000000.00', '50000.00', '50000.00'],
            ['reteiva', '190000.00', '28500.00', '28500.00'],
            ['reteica', '1000000.00', '9660.00', '9660.00'],
        ], array_map(static fn (WithholdingGroup $g) => [$g->kind->value, $g->totalBase->toString(), $g->totalAmount->toString(), $g->ledgerAmount->toString()], $report->totals), 'One total per kind over the range.');

        $row = $report->groups[0]->rows[0];
        self::assertSame([$this->supplier->toRfc4122(), 'nit', 'Servicios Andinos S.A.S.', 'ReteFuente compras 2,5 %', 'percentage', '2.5000', 1], [$row->terceroId, $row->identificationType, $row->terceroName, $row->taxName, $row->calculation, $row->rate, $row->documents], 'The row names the supplier and the tax as the document copied it.');
    }

    public function testTheSameSupplierAndTaxAddUpIntoOneRowCountingItsInvoices(): void
    {
        $other = $this->tercero('Ferretería Central Ltda.');
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::today());
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::today());
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::today(), $other);

        $rows = $this->report(WithholdingSide::Practiced)->groups[0]->rows;

        self::assertSame([
            ['Ferretería Central Ltda.', '1000000.00', '25000.00', 1],
            ['Servicios Andinos S.A.S.', '2000000.00', '50000.00', 2],
        ], array_map(static fn (WithholdingLine $l) => [$l->terceroName, $l->base, $l->amount, $l->documents], $rows), 'One row per tercero and tax, by name.');
    }

    public function testAVoidInTheSameMonthLeavesTheInvoiceOutAndTheBooksAgree(): void
    {
        $kept = $this->purchaseWith([$this->retefuente()], '1165000.00', self::today());
        $voided = $this->purchaseWith([$this->retefuente(), $this->reteIva()], '1136500.00', self::today());
        $this->voidInvoice('purchase-invoices', $voided['id']);

        $report = $this->report(WithholdingSide::Practiced);

        self::assertSame([
            [self::month(), 'retefuente', '1000000.00', '25000.00', '25000.00', '0.00'],
        ], self::groupsOf($report), 'The voided invoice is not in the report, and its reversing entry nets its retenciones out of the books.');
        self::assertSame(1, $report->voidedCount, 'It is counted in the footnote.');
        self::assertSame(1, $report->groups[0]->rows[0]->documents, 'Only '.$kept['number'].' is counted.');
    }

    public function testAVoidInALaterMonthIsTheDifferenceWithTheBooks(): void
    {
        $voided = $this->purchaseWith([$this->retefuente()], '1165000.00', self::monthStart(-1));
        $this->voidInvoice('purchase-invoices', $voided['id']);

        $report = $this->report(WithholdingSide::Practiced, -1, 0);

        self::assertSame([
            [self::month(-1), 'retefuente', '0.00', '0.00', '25000.00', '25000.00'],
            [self::month(), 'retefuente', '0.00', '0.00', '-25000.00', '-25000.00'],
        ], self::groupsOf($report), 'Last month the books hold the retención the report leaves out; this month the reversal takes it back.');
        self::assertSame(1, $report->voidedCount);
        self::assertSame('0.00', $report->totals[0]->difference()->toString(), 'Over both months they agree.');
    }

    public function testDraftsNeverCount(): void
    {
        $this->createDraft(['issue_date' => self::today()]);

        $report = $this->report(WithholdingSide::Practiced);

        self::assertSame([], $report->groups);
        self::assertSame(0, $report->voidedCount);
    }

    public function testTheKindFilterKeepsOnlyThatKind(): void
    {
        $this->purchaseWith([$this->retefuente(), $this->reteIca(), $this->reteIva()], '1126840.00', self::today());

        $report = $this->report(WithholdingSide::Practiced, 0, 0, WithholdingKind::Vat);

        self::assertSame([[self::month(), 'reteiva', '190000.00', '28500.00', '28500.00', '0.00']], self::groupsOf($report));
        self::assertCount(1, $report->totals);
    }

    public function testRetencionesSufridasMirrorTheSalesInvoices(): void
    {
        $client = $this->newClient();
        $this->saleWith($client, [$this->retefuente(), $this->reteIca(), $this->reteIva()], '1126840.00', self::today());
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::today());

        $report = $this->report(WithholdingSide::Suffered);

        self::assertSame([
            [self::month(), 'retefuente', '1000000.00', '25000.00', '25000.00', '0.00'],
            [self::month(), 'reteiva', '190000.00', '28500.00', '28500.00', '0.00'],
            [self::month(), 'reteica', '1000000.00', '9660.00', '9660.00', '0.00'],
        ], self::groupsOf($report), 'What the client withheld, against 135515/135517/135518; the purchase is not here.');
        self::assertSame('Cliente Uno S.A.S.', $report->groups[0]->rows[0]->terceroName);
        self::assertSame('800197268', $report->groups[0]->rows[0]->identificationNumber);
    }

    public function testTheIvaReportAddsTheLinesByRateAndMatchesTheBooks(): void
    {
        $client = $this->newClient();
        $this->saleWith($client, [], '1190000.00', self::monthStart(-1));
        $this->saleWith($client, [$this->reteIva()], '1161500.00', self::today());
        $this->saleWith($client, [], '1050000.00', self::today(), 'IVA 5 %');
        $this->purchaseWith([], '1190000.00', self::today());
        $voided = $this->purchaseWith([$this->reteIva()], '1161500.00', self::today());
        $this->voidInvoice('purchase-invoices', $voided['id']);

        $vat = $this->reports()->vat($this->company, MonthRange::of(self::month(-1), self::month(), new \DateTimeImmutable()));

        self::assertSame([['19.0000', '2000000.00', '380000.00'], ['5.0000', '1000000.00', '50000.00']], array_map(static fn ($r) => [$r->rate, $r->base, $r->tax], $vat->generated), 'IVA generado by rate, the highest first.');
        self::assertSame([['19.0000', '1000000.00', '190000.00']], array_map(static fn ($r) => [$r->rate, $r->base, $r->tax], $vat->deductible), 'IVA descontable: the voided purchase is left out.');
        self::assertSame(['430000.00', '190000.00', '0.00', '28500.00'], [$vat->generatedTotal->toString(), $vat->deductibleTotal->toString(), $vat->reteivaPracticed->toString(), $vat->reteivaSuffered->toString()]);
        self::assertSame('211500.00', $vat->net()->toString(), 'Generado − descontable − ReteIVA sufrida: a pagar.');
        self::assertSame(['430000.00', '190000.00'], [$vat->ledgerGenerated->toString(), $vat->ledgerDeductible->toString()], 'Según libros: 240805 and 240810 moved the same.');
        self::assertSame(['0.00', '0.00'], [$vat->generatedDifference()->toString(), $vat->deductibleDifference()->toString()]);
        self::assertSame(1, $vat->voidedCount);
    }

    public function testAnotherCompanysInvoicesNeverCount(): void
    {
        $this->purchaseWith([$this->retefuente()], '1165000.00', self::today());
        $mine = $this->company;
        $this->signOut();
        $this->startPurchasing('otra@empresa.co', '900999888', 'Otra S.A.S.');
        self::assertNotEquals($mine, $this->company);

        self::assertSame([], $this->report(WithholdingSide::Practiced)->groups);
        $vat = $this->reports()->vat($this->company, MonthRange::of(self::month(), null, new \DateTimeImmutable()));
        self::assertSame([[], [], '0.00'], [$vat->generated, $vat->deductible, $vat->ledgerDeductible->toString()]);
    }
}
