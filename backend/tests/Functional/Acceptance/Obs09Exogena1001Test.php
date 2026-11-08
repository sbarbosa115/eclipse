<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Reporting\TaxReportFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Observations PRD §8, OBS-9: "Exógena 1001 for the year lists every supplier with pagos, retefuente and reteIVA by
 * account and concept, groups rows under the threshold into 222222222, and refuses to export while a listed tercero
 * lacks a municipio."
 */
final class Obs09Exogena1001Test extends ApiTestCase
{
    use TaxReportFixtures;

    public function testFormat1001ByAccountAndConceptWithItsCuantiasMenoresAndTheMunicipioRule(): void
    {
        $this->startReporting();
        $small = $this->tercero('Papelería La Esquina');
        $this->db()->update('tercero', ['municipality_code' => '11001'], ['id' => $this->supplier->toBinary()]);
        // A big supplier on two accounts (servicios 513595, honorarios 511025) and a small one, all in this year.
        $this->purchaseWith([$this->retefuente(), $this->reteIva()], '1136500.00', self::today());
        $honorarios = $this->payload()['lines'][0];
        $honorarios['account_id'] = $this->account('511025')->toRfc4122();
        $honorarios['withholding_tax_id'] = $this->taxId('ReteFuente honorarios 10 %', 'withholding');
        $this->emitInvoice(['supplier_invoice_number' => 'HON-1', 'issue_date' => self::today(), 'lines' => [$honorarios],
            'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1090000.00', 'due_date' => self::today(30)]]]);
        $paper = $this->payload()['lines'][0];
        $paper['unit_price'] = '100000';
        $paper['withholding_tax_id'] = null;
        $this->emitInvoice(['tercero_id' => $small->toRfc4122(), 'supplier_invoice_number' => 'P-77', 'issue_date' => self::today(), 'lines' => [$paper],
            'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '119000.00', 'due_date' => self::today(30)]]]);
        $this->signInAs('accountant');
        $year = substr(self::today(), 0, 4);

        $all = $this->getJson("/api/v1/reports/taxes/exogena/1001?year=$year");
        self::assertSame([
            ['Papelería La Esquina', '513595', '5003', ['payment' => '100000.00', 'vat_cost' => '0.00', 'withholding' => '0.00', 'vat_withholding' => '0.00']],
            ['Servicios Andinos S.A.S.', '511025', '5001', ['payment' => '1000000.00', 'vat_cost' => '0.00', 'withholding' => '100000.00', 'vat_withholding' => '0.00']],
            ['Servicios Andinos S.A.S.', '513595', '5003', ['payment' => '1000000.00', 'vat_cost' => '0.00', 'withholding' => '25000.00', 'vat_withholding' => '28500.00']],
        ], array_map(static fn (array $r) => [$r['business_name'], $r['account_code'], $r['concept_code'], $r['values']], $all['rows']), 'Every supplier, by account and concept (honorarios 5001, servicios 5003).');
        self::assertSame(1, $all['missing_municipality'], 'The small supplier has no municipio.');

        $this->client->request('GET', "/api/v1/reports/taxes/exogena/1001/export?year=$year&format=csv");
        self::assertResponseStatusCodeSame(422);
        self::assertSame('exogena_terceros_incomplete', $this->body()['error'], 'Refused while a listed tercero lacks a municipio.');

        $grouped = $this->getJson("/api/v1/reports/taxes/exogena/1001?year=$year&threshold=500000");
        self::assertSame(['Servicios Andinos S.A.S.', 'Servicios Andinos S.A.S.'], array_column($grouped['rows'], 'business_name'));
        self::assertSame([['222222222', '5003', '100000.00']], array_map(static fn (array $r) => [$r['identification_number'], $r['concept_code'], $r['values']['payment']], $grouped['minor_amounts_rows']), 'The small supplier is a cuantía menor.');
        self::assertSame(0, $grouped['missing_municipality']);
        $this->client->request('GET', "/api/v1/reports/taxes/exogena/1001/export?year=$year&threshold=500000&format=csv");
        self::assertResponseIsSuccessful('Once nobody listed by name lacks a municipio, the paper exports.');
    }
}
