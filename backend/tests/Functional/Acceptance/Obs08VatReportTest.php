<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Reporting\TaxReportFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Observations PRD §8, OBS-8: "The IVA report for a bimester equals Σ sales lines' IVA and Σ purchase lines' IVA of
 * emitted documents in it, and its *Según libros* row matches 240805/240810 for the period."
 */
final class Obs08VatReportTest extends ApiTestCase
{
    use TaxReportFixtures;

    public function testABimestersIvaEqualsItsLinesAndTheBooks(): void
    {
        $this->startReporting();
        $client = $this->newClient();
        $this->saleWith($client, [], '1190000.00', self::monthStart(-1));
        $this->saleWith($client, [], '1050000.00', self::today(), 'IVA 5 %');
        $this->purchaseWith([], '1190000.00', self::monthStart(-1));
        $this->purchaseWith([], '1190000.00', self::today());
        $this->createDraft(['issue_date' => self::today()]);
        $outside = $this->saleWith($client, [], '1190000.00', self::monthStart(-2));
        self::assertNotSame('', $outside['number']);

        $vat = $this->getJson('/api/v1/reports/taxes/vat?from='.self::month(-1).'&to='.self::month());
        self::assertResponseIsSuccessful();

        $lines = fn (string $table, string $invoice) => (string) $this->db()->fetchOne(
            "SELECT COALESCE(SUM(l.tax_amount), 0) FROM $table l JOIN $invoice i ON i.id = l.document_id
             WHERE i.company_id = ? AND l.charge_kind = 'iva' AND i.status IN ('emitted', 'partially_paid', 'paid') AND i.issue_date BETWEEN ? AND ?",
            [$this->company->toBinary(), self::monthStart(-1), self::today()],
        );
        self::assertSame([$lines('sales_invoice_line', 'sales_invoice'), $lines('purchase_invoice_line', 'purchase_invoice')], [$vat['generated_total'], $vat['deductible_total']], 'Σ of the emitted lines\' IVA in the bimester; the draft and the earlier month are not in it.');
        self::assertSame(['240000.00', '380000.00'], [$vat['generated_total'], $vat['deductible_total']]);
        self::assertSame([$this->ledgerMoved('240805', true), $this->ledgerMoved('240810', false)], [$vat['ledger_generated'], $vat['ledger_deductible']], 'Según libros is what 240805 and 240810 moved in the period.');
        self::assertSame(['0.00', '0.00'], [$vat['generated_difference'], $vat['deductible_difference']]);
        self::assertSame('-140000.00', $vat['net'], 'A saldo a favor.');
    }

    private function ledgerMoved(string $prefix, bool $credit): string
    {
        $moved = (string) $this->db()->fetchOne(
            'SELECT COALESCE(SUM('.($credit ? 'l.credit - l.debit' : 'l.debit - l.credit').'), 0) FROM journal_line l JOIN journal_entry e ON e.id = l.entry_id
             WHERE l.company_id = ? AND l.account_code LIKE ? AND e.entry_date BETWEEN ? AND ?',
            [$this->company->toBinary(), $prefix.'%', self::monthStart(-1), self::today()],
        );

        return \App\Shared\Domain\Money\Money::of($moved)->toString();
    }
}
