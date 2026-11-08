<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Reporting\TaxReportFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Observations PRD §8, OBS-3, the report half: "the retenciones practicadas report for the month shows the supplier
 * with base 1.000.000 and each retención, and *Según libros* equals the report." (The posting half is item 2's,
 * Obs03PurchaseInvoicePostsEachRetencionTest.)
 */
final class Obs03RetencionesPracticadasReportTest extends ApiTestCase
{
    use TaxReportFixtures;

    public function testTheMonthsReportShowsTheSupplierWithEachRetencionAndTheBooksAgree(): void
    {
        $this->startReporting();
        $this->purchaseWith([$this->retefuente(), $this->reteIca(), $this->reteIva()], '1126840.00', self::today());
        $this->signInAs('accountant');

        $report = $this->getJson('/api/v1/reports/taxes/withholdings/practiced?from='.self::month().'&to='.self::month());

        self::assertResponseIsSuccessful();
        $seen = array_map(static fn (array $g) => [
            $g['kind'],
            $g['rows'][0]['tercero_name'],
            $g['rows'][0]['tax_name'],
            $g['rows'][0]['base'],
            $g['rows'][0]['amount'],
            $g['ledger_amount'],
            $g['difference'],
        ], $report['groups']);
        self::assertSame([
            ['retefuente', 'Servicios Andinos S.A.S.', 'ReteFuente compras 2,5 %', '1000000.00', '25000.00', '25000.00', '0.00'],
            ['reteiva', 'Servicios Andinos S.A.S.', 'ReteIVA 15 %', '190000.00', '28500.00', '28500.00', '0.00'],
            ['reteica', 'Servicios Andinos S.A.S.', 'ReteICA 0,966 %', '1000000.00', '9660.00', '9660.00', '0.00'],
        ], $seen, 'The supplier with base 1.000.000 (ReteIVA on the IVA) and each retención; Según libros (2365, 2367, 2368) equals the report.');
    }
}
