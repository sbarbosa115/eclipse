<?php

namespace App\Tests\Functional\Reporting;

use App\Tests\Support\ApiTestCase;

/**
 * GET /reports/taxes/exogena/{format} and its export (observations PRD §4.7 d, §6): the exógena working papers of a
 * taxable year, per tercero (and account and concept in 1001 and 1007), with the cuantías menores grouped under the
 * threshold, built from the emitted invoices and the cartera at 31/12. VIEW_BOOKS; the export is refused while a
 * tercero of the format has no municipio.
 *
 * The year's documents: a purchase from Servicios Andinos (513595, 1.000.000 + IVA, Retefuente 2,5 % and ReteIVA 15 %,
 * on credit; created without a municipio) and a sale to Cliente Uno (Bogotá; Consultoría 1.000.000 + IVA, Retefuente
 * 2,5 % sufrida, on credit).
 */
final class ExogenaApiTest extends ApiTestCase
{
    use TaxReportFixtures;

    private string $clientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
        $this->db()->update('tercero', ['address' => 'Calle 10 # 5-20'], ['id' => $this->supplier->toBinary()]);
        $this->purchaseWith([$this->retefuente(), $this->reteIva()], '1136500.00', self::today());
        $this->clientId = $this->newClient();
        $this->saleWith($this->clientId, [$this->retefuente()], '1165000.00', self::today());
    }

    private static function year(): int
    {
        return (int) substr(self::today(), 0, 4);
    }

    /** @return array<string, mixed> */
    private function exogena(string $format, string $query = ''): array
    {
        $body = $this->getJson("/api/v1/reports/taxes/exogena/$format?year=".self::year().$query);
        self::assertResponseIsSuccessful("Format $format: ".json_encode($body));

        return $body;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function figures(array $row): array
    {
        return ['name' => $row['business_name'], 'account' => $row['account_code'], 'concept' => $row['concept_code'], 'values' => $row['values']];
    }

    private function giveTheSupplierAMunicipality(): void
    {
        $this->db()->update('tercero', ['municipality_code' => '05001'], ['id' => $this->supplier->toBinary()]);
    }

    /** @return list<list<string>> */
    private function csv(string $uri): array
    {
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful($uri.': '.$this->client->getResponse()->getContent());
        self::assertStringStartsWith('text/csv; charset=UTF-8', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $content = $this->client->getInternalResponse()->getContent();

        return array_map(static fn (string $line) => array_map(static fn (?string $cell) => (string) $cell, str_getcsv($line, ';', '"', '')), explode("\r\n", rtrim(substr($content, 3), "\r\n")));
    }

    public function testFormat1001ListsTheSupplierByAccountAndConcept(): void
    {
        $this->signInAs('accountant');

        $body = $this->exogena('1001');

        self::assertSame([self::year(), '1001', '0.00', ['payment', 'vat_cost', 'withholding', 'vat_withholding'], [], 0, 1], [
            $body['year'], $body['format'], $body['threshold'], $body['value_columns'], $body['minor_amounts_rows'], $body['missing_concepts'], $body['missing_municipality'],
        ], 'The supplier has no municipio yet.');
        self::assertSame([[
            'name' => 'Servicios Andinos S.A.S.',
            'account' => '513595',
            'concept' => '5003',
            'values' => ['payment' => '1000000.00', 'vat_cost' => '0.00', 'withholding' => '25000.00', 'vat_withholding' => '28500.00'],
        ]], array_map(self::figures(...), $body['rows']), 'Pago, retefuente and ReteIVA by the account the line posted to; 5135 → 5003 by default.');
        self::assertSame(['payment' => '1000000.00', 'vat_cost' => '0.00', 'withholding' => '25000.00', 'vat_withholding' => '28500.00'], $body['totals']);
    }

    public function testEveryOtherFormatReadsItsOwnFigures(): void
    {
        $expected = [
            '1003' => ['Cliente Uno S.A.S.', null, null, ['base' => '1000000.00', 'withholding' => '25000.00']],
            '1005' => ['Servicios Andinos S.A.S.', null, null, ['deductible_vat' => '190000.00', 'returned_vat' => '0.00']],
            '1006' => ['Cliente Uno S.A.S.', null, null, ['generated_vat' => '190000.00', 'consumption_tax' => '0.00']],
            '1007' => ['Cliente Uno S.A.S.', '413595', '4001', ['gross_income' => '1000000.00', 'returns' => '0.00']],
            '1008' => ['Cliente Uno S.A.S.', null, null, ['balance' => '1165000.00']],
            '1009' => ['Servicios Andinos S.A.S.', null, null, ['balance' => '1136500.00']],
        ];
        foreach ($expected as $format => [$name, $account, $concept, $values]) {
            $body = $this->exogena((string) $format);
            self::assertSame(array_keys($values), $body['value_columns'], "$format's columns.");
            self::assertSame([['name' => $name, 'account' => $account, 'concept' => $concept, 'values' => $values]], array_map(self::figures(...), $body['rows']), "Format $format.");
        }
    }

    public function testARowCarriesTheDianIdentificationColumns(): void
    {
        $row = $this->exogena('1006')['rows'][0];

        self::assertSame([
            'tercero_id' => $this->clientId,
            'document_type_code' => '31',
            'identification_number' => '800197268',
            'last_names' => null,
            'first_names' => null,
            'business_name' => 'Cliente Uno S.A.S.',
            'department_code' => '11',
            'municipality_code' => '11001',
            'country_code' => 'CO',
        ], array_intersect_key($row, array_flip(['tercero_id', 'document_type_code', 'identification_number', 'last_names', 'first_names', 'business_name', 'department_code', 'municipality_code', 'country_code'])));
        self::assertArrayHasKey('check_digit', $row);
        self::assertArrayHasKey('address', $row);
    }

    public function testTheCarteraIsTheBalanceAtTheEndOfTheYear(): void
    {
        $this->owed($this->supplier, 'FAC-ANT', ['issue_date' => (self::year() - 1).'-12-15', 'due_date' => self::year().'-01-15', 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1150000.00', 'due_date' => self::year().'-01-15']]]);

        $lastYear = $this->getJson('/api/v1/reports/taxes/exogena/1009?year='.(self::year() - 1));

        self::assertSame([['balance' => '1150000.00']], array_column($lastYear['rows'], 'values'), 'At 31/12 of last year only last December\'s invoice was owed.');
        self::assertSame([['balance' => '2286500.00']], array_column($this->exogena('1009')['rows'], 'values'));
    }

    public function testAThresholdGroupsTheCuantiasMenoresUnder222222222(): void
    {
        $body = $this->exogena('1001', '&threshold=1000001');

        self::assertSame('1000001.00', $body['threshold']);
        self::assertSame([], $body['rows'], 'A supplier paid 1.000.000 is under 1.000.001.');
        self::assertCount(1, $body['minor_amounts_rows']);
        $minor = $body['minor_amounts_rows'][0];
        self::assertSame(['43', '222222222', 'Cuantías menores', null, '5003', 'CO'], [$minor['document_type_code'], $minor['identification_number'], $minor['business_name'], $minor['tercero_id'], $minor['concept_code'], $minor['country_code']]);
        self::assertSame(['payment' => '1000000.00', 'vat_cost' => '0.00', 'withholding' => '25000.00', 'vat_withholding' => '28500.00'], $minor['values']);
        self::assertSame($minor['values'], $body['totals'], 'The totals count the cuantías menores too.');
        self::assertSame(0, $body['missing_municipality'], 'A tercero grouped under 222222222 is not reported by name: its municipio is not needed.');

        self::assertCount(1, $this->exogena('1001', '&threshold=1000000')['rows'], 'Exactly the threshold is reported by name.');
    }

    public function testAnAccountWithoutAConceptIsLeftBlankAndCounted(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => [['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5001']]]);

        $body = $this->exogena('1001');

        self::assertNull($body['rows'][0]['concept_code']);
        self::assertSame(1, $body['missing_concepts']);
        self::assertSame(1, $this->exogena('1007')['missing_concepts'], 'Nothing covers 413595 in 1007 any more.');
        self::assertSame(0, $this->exogena('1006')['missing_concepts'], 'A format without concepts never misses one.');

        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => [
            ['format' => '1001', 'account_prefix' => '51', 'concept_code' => '5016'],
            ['format' => '1001', 'account_prefix' => '5135', 'concept_code' => '5003'],
            ['format' => '1001', 'account_prefix' => '513595', 'concept_code' => '5002'],
        ]]);
        self::assertSame('5002', $this->exogena('1001')['rows'][0]['concept_code'], 'The longest prefix wins.');
    }

    public function testATerceroWithoutMunicipioBlocksTheExport(): void
    {
        foreach (['?format=csv', '?format=pdf', '?format=csv&check=1'] as $query) {
            $this->client->request('GET', '/api/v1/reports/taxes/exogena/1001/export'.$query.'&year='.self::year());
            self::assertResponseStatusCodeSame(422, $query);
            self::assertSame('exogena_terceros_incomplete', $this->body()['error'], $query);
        }
        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1003/export?year='.self::year().'&check=1');
        self::assertResponseStatusCodeSame(204, 'The client of 1003 has its municipio: that format exports.');

        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1001/export?year='.self::year().'&threshold=2000000&check=1');
        self::assertResponseStatusCodeSame(204, 'Grouped under 222222222, the supplier no longer blocks it.');

        $this->db()->update('tercero', ['country_code' => 'US', 'city' => 'Miami'], ['id' => $this->supplier->toBinary()]);
        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1001/export?year='.self::year().'&check=1');
        self::assertResponseStatusCodeSame(204, 'A foreign tercero has no municipio to give.');
    }

    public function testTheWorkingPaperExportsToCsvAndPdf(): void
    {
        $this->giveTheSupplierAMunicipality();

        $rows = $this->csv('/api/v1/reports/taxes/exogena/1001/export?format=csv&year='.self::year());

        self::assertSame(['Concepto', 'Cuenta', 'Tipo de documento', 'Número de identificación', 'DV', 'Primer apellido y segundo apellido', 'Nombres', 'Razón social', 'Dirección', 'Código departamento', 'Código municipio', 'País', 'Pago o abono en cuenta', 'IVA mayor valor del costo', 'Retención en la fuente practicada', 'Retención de IVA practicada'], $rows[0]);
        self::assertSame(['5003', '513595', '31'], \array_slice($rows[1], 0, 3));
        self::assertSame(['Servicios Andinos S.A.S.', 'Calle 10 # 5-20', '05', '05001', '169', '1000000.00', '0.00', '25000.00', '28500.00'], \array_slice($rows[1], 7), 'The DIAN country code of Colombia is 169.');
        self::assertSame(['Total', '', '', '', '', '', '', '', '', '', '', '', '1000000.00', '0.00', '25000.00', '28500.00'], $rows[2], 'The totals row closes the paper.');
        self::assertCount(3, $rows);
        self::assertStringContainsString('filename=exogena-1001-'.self::year().'.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $withMinor = $this->csv('/api/v1/reports/taxes/exogena/1001/export?format=csv&year='.self::year().'&threshold=2000000');
        self::assertSame(['5003', '', '43', '222222222', '', '', '', 'Cuantías menores'], \array_slice($withMinor[1], 0, 8));

        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1008/export?format=pdf&year='.self::year());
        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function testVoidedInvoicesAreLeftOut(): void
    {
        $sales = $this->getJson('/api/v1/sales-invoices')['items'];
        $this->voidInvoice('sales-invoices', $sales[0]['id']);

        self::assertSame([], $this->exogena('1006')['rows']);
        self::assertSame([], $this->exogena('1008')['rows'], 'Nothing is owed on a voided invoice.');
    }

    public function testNoYearIsLastYearAndBadFiltersAreRefused(): void
    {
        $body = $this->getJson('/api/v1/reports/taxes/exogena/1001');
        self::assertSame([self::year() - 1, []], [$body['year'], $body['rows']], 'The return is filed for the year that ended.');

        foreach (['?year=26' => 'invalid_year', '?year=1999' => 'invalid_year', '?year='.(self::year() + 1) => 'invalid_year', '?threshold=-5' => 'invalid_threshold', '?threshold=mil' => 'invalid_threshold', '/export?format=xlsx' => 'invalid_format'] as $query => $error) {
            $this->client->request('GET', '/api/v1/reports/taxes/exogena/1001'.$query);
            self::assertResponseStatusCodeSame(400, $query);
            self::assertSame($error, $this->body()['error'], $query);
        }
        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1004');
        self::assertResponseStatusCodeSame(404, 'Formats Mustang does not produce are not routes.');
    }

    public function testOnlyWhoMayViewTheBooksReadsThem(): void
    {
        $this->signInAs('billing');
        foreach (['/1001', '/1009', '/1001/export?check=1'] as $path) {
            $this->client->request('GET', '/api/v1/reports/taxes/exogena'.$path);
            self::assertResponseStatusCodeSame(403, "Billing does not read $path.");
        }
    }

    public function testAnotherCompanySeesNothingOfOurs(): void
    {
        $this->signOut();
        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');

        foreach (['1001', '1003', '1005', '1006', '1007', '1008', '1009'] as $format) {
            $body = $this->exogena($format);
            self::assertSame([[], 0], [$body['rows'], $body['missing_municipality']], "Format $format of another company.");
        }
        $this->client->request('GET', '/api/v1/reports/taxes/exogena/1001/export?year='.self::year());
        self::assertResponseIsSuccessful('Our supplier without municipio does not block their export.');
    }
}
