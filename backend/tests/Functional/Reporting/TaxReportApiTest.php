<?php

namespace App\Tests\Functional\Reporting;

use App\Tests\Support\ApiTestCase;

/**
 * GET /reports/taxes/withholdings/{practiced|suffered}, /reports/taxes/vat and their exports (observations PRD §4.7,
 * §6): the accountant's working papers, for who may view the books only.
 */
final class TaxReportApiTest extends ApiTestCase
{
    use TaxReportFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
        $this->purchaseWith([$this->retefuente(), $this->reteIva()], '1136500.00', self::today());
    }

    /** @return list<list<string>> */
    private function csv(string $uri): array
    {
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful($uri);
        $response = $this->client->getResponse();
        self::assertStringStartsWith('text/csv; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        $content = $this->client->getInternalResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content, 'UTF-8 with a BOM.');

        return array_map(static fn (string $line) => array_map(static fn (?string $cell) => (string) $cell, str_getcsv($line, ';', '"', '')), explode("\r\n", rtrim(substr($content, 3), "\r\n")));
    }

    public function testTheAccountantReadsRetencionesPracticadas(): void
    {
        $this->signInAs('accountant');

        $body = $this->getJson('/api/v1/reports/taxes/withholdings/practiced?from='.self::month().'&to='.self::month());

        self::assertResponseIsSuccessful();
        self::assertSame([self::month(), self::month(), 'practiced', null, 0], [$body['from'], $body['to'], $body['side'], $body['kind'], $body['voided_count']]);
        self::assertCount(2, $body['groups']);
        $group = $body['groups'][0];
        self::assertSame(['month' => self::month(), 'kind' => 'retefuente', 'total_base' => '1000000.00', 'total_amount' => '25000.00', 'ledger_amount' => '25000.00', 'difference' => '0.00'], array_diff_key($group, ['rows' => 1]), 'Decimal strings, snake_case.');
        $row = $group['rows'][0];
        self::assertSame(['tercero_id', 'identification_type', 'identification_number', 'check_digit', 'tercero_name', 'tax_id', 'tax_name', 'calculation', 'rate', 'base', 'amount', 'documents'], array_keys($row));
        self::assertSame(['Servicios Andinos S.A.S.', 'ReteFuente compras 2,5 %', '2.5000', '1000000.00', '25000.00', 1], [$row['tercero_name'], $row['tax_name'], $row['rate'], $row['base'], $row['amount'], $row['documents']]);
        self::assertSame(['retefuente', 'reteiva', 'reteica'], array_column($body['totals'], 'kind'), 'A total per kind, even one without rows.');
    }

    public function testNoPeriodIsThisMonthAndTheKindFilters(): void
    {
        $body = $this->getJson('/api/v1/reports/taxes/withholdings/practiced?kind=reteiva');

        self::assertSame([self::month(), self::month(), 'reteiva'], [$body['from'], $body['to'], $body['kind']]);
        self::assertSame(['reteiva'], array_column($body['groups'], 'kind'));
        self::assertSame('28500.00', $body['groups'][0]['total_amount']);

        self::assertSame([], $this->getJson('/api/v1/reports/taxes/withholdings/suffered')['groups'], 'Nothing was sold.');
    }

    public function testTheVatReport(): void
    {
        $body = $this->getJson('/api/v1/reports/taxes/vat?from='.self::month(-1).'&to='.self::month());

        self::assertResponseIsSuccessful();
        self::assertSame([self::month(-1), self::month()], [$body['from'], $body['to']], 'A bimester is a range.');
        self::assertSame([], $body['generated']);
        self::assertSame([['rate' => '19.0000', 'base' => '1000000.00', 'tax' => '190000.00']], $body['deductible']);
        self::assertSame(
            ['0.00', '190000.00', '28500.00', '0.00', '0.00', '-190000.00', '0.00', '190000.00', '0.00', '0.00', 0],
            [$body['generated_total'], $body['deductible_total'], $body['reteiva_practiced'], $body['reteiva_suffered'], $body['impoconsumo'], $body['net'], $body['ledger_generated'], $body['ledger_deductible'], $body['generated_difference'], $body['deductible_difference'], $body['voided_count']],
            'A saldo a favor is negative.',
        );
    }

    public function testOnlyWhoMayViewTheBooksReadsThem(): void
    {
        $this->signInAs('billing');
        foreach (['/withholdings/practiced', '/withholdings/suffered', '/vat', '/withholdings/practiced/export', '/vat/export?check=1'] as $path) {
            $this->client->request('GET', '/api/v1/reports/taxes'.$path);
            self::assertResponseStatusCodeSame(403, "Billing does not read $path.");
        }

        $this->signOut();
        $this->client->request('GET', '/api/v1/reports/taxes/vat');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnotherCompanySeesNothingOfOurs(): void
    {
        $this->signOut();
        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');

        self::assertSame([], $this->getJson('/api/v1/reports/taxes/withholdings/practiced')['groups']);
        self::assertSame([], $this->getJson('/api/v1/reports/taxes/vat')['deductible']);
        $rows = $this->csv('/api/v1/reports/taxes/withholdings/practiced/export');
        self::assertCount(1, $rows, 'The header only.');
    }

    public function testABadPeriodOrKindIsRefused(): void
    {
        foreach ([
            '/withholdings/practiced?from=2026-13' => 'invalid_date',
            '/vat?to=noviembre' => 'invalid_date',
            '/vat?from=2026-11&to=2026-10' => 'invalid_period',
            '/withholdings/suffered?from=2025-01&to=2026-01' => 'invalid_period',
            '/withholdings/practiced?kind=iva' => 'invalid_kind',
            '/vat/export?format=xlsx' => 'invalid_format',
        ] as $path => $error) {
            $this->client->request('GET', '/api/v1/reports/taxes'.$path);
            self::assertResponseStatusCodeSame(400, $path);
            self::assertSame($error, $this->body()['error'], $path);
        }
        $this->getJson('/api/v1/reports/taxes/vat?from=2025-01&to=2025-12');
        self::assertResponseIsSuccessful('Twelve months is the longest range.');
    }

    public function testRetencionesExportToCsvWithTheirSubtotalsAndTheBooks(): void
    {
        $rows = $this->csv('/api/v1/reports/taxes/withholdings/practiced/export?format=csv&from='.self::month().'&to='.self::month());

        self::assertSame(['Mes', 'Tipo de retención', 'Tipo de documento', 'Número', 'DV', 'Proveedor', 'Impuesto', 'Tarifa', 'Base', 'Retención', 'Documentos'], $rows[0]);
        self::assertSame([self::month(), 'Retefuente', 'NIT'], \array_slice($rows[1], 0, 3));
        self::assertSame(['Servicios Andinos S.A.S.', 'ReteFuente compras 2,5 %', '2,5 %', '1000000.00', '25000.00', '1'], \array_slice($rows[1], 5));
        self::assertSame([self::month(), 'Retefuente', '', '', '', 'Total Retefuente', '', '', '1000000.00', '25000.00', ''], $rows[2]);
        self::assertSame(['Según libros (2365)', '25000.00'], [$rows[3][5], $rows[3][9]]);
        self::assertSame(['Diferencia', '0.00'], [$rows[4][5], $rows[4][9]]);
        self::assertSame(['ReteIVA', 'ReteIVA 15 %', '15 %', '190000.00', '28500.00'], [$rows[5][1], $rows[5][6], $rows[5][7], $rows[5][8], $rows[5][9]]);
        self::assertCount(9, $rows, 'Header, then a row and its three closing lines per kind.');
        self::assertStringContainsString('filename=retenciones-practicadas-'.self::month().'.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testTheVatExportsToCsvAndBothToPdf(): void
    {
        $rows = $this->csv('/api/v1/reports/taxes/vat/export');

        self::assertSame(['Concepto', 'Tarifa', 'Base', 'Valor'], $rows[0]);
        self::assertContains(['IVA descontable', '19 %', '1000000.00', '190000.00'], $rows);
        self::assertContains(['Según libros (240810)', '', '', '190000.00'], $rows);
        self::assertSame(['Saldo a favor', '', '', '190000.00'], end($rows));

        foreach (['withholdings/suffered' => 'retenciones-sufridas', 'withholdings/practiced' => 'retenciones-practicadas', 'vat' => 'iva'] as $report => $file) {
            $this->client->request('GET', "/api/v1/reports/taxes/$report/export?format=pdf&from=".self::month(-1).'&to='.self::month());
            self::assertResponseIsSuccessful($report);
            self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
            self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
            self::assertStringContainsString("filename=$file-".self::month(-1).'-a-'.self::month().'.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        }
    }

    public function testACheckRehearsesTheExport(): void
    {
        $this->client->request('GET', '/api/v1/reports/taxes/withholdings/practiced/export?format=pdf&check=1');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/api/v1/reports/taxes/vat/export?format=csv&check=1');
        self::assertResponseStatusCodeSame(204);
    }

    public function testAReportAboveTheCapIsRefusedNotCutShort(): void
    {
        // Each supplier is a row of its own: past the PDF cap with its subtotal lines.
        $db = $this->db();
        $line = $db->fetchAssociative('SELECT l.* FROM purchase_invoice_line_withholding l WHERE l.company_id = ? AND l.kind = ?', [$this->company->toBinary(), 'retefuente']) ?: [];
        $invoice = $db->fetchAssociative('SELECT i.* FROM purchase_invoice i WHERE i.company_id = ?', [$this->company->toBinary()]) ?: [];
        $invoiceLine = $db->fetchAssociative('SELECT * FROM purchase_invoice_line WHERE company_id = ?', [$this->company->toBinary()]) ?: [];
        $tercero = $db->fetchAssociative('SELECT * FROM tercero WHERE id = ?', [$this->supplier->toBinary()]) ?: [];
        for ($i = 0; $i < \App\Reporting\Application\Export\ExportLimits::PDF_ROWS; ++$i) {
            $supplier = ['id' => random_bytes(16), 'identification_number' => (string) (700000000 + $i), 'display_name' => "Proveedor $i", 'business_name' => "Proveedor $i"] + $tercero;
            $db->insert('tercero', $supplier);
            $copy = ['id' => random_bytes(16), 'tercero_id' => $supplier['id'], 'number' => "R-$i", 'active_number_key' => "R-$i", 'journal_entry_id' => null] + $invoice;
            $db->insert('purchase_invoice', $copy);
            $lineCopy = ['id' => random_bytes(16), 'document_id' => $copy['id']] + $invoiceLine;
            $db->insert('purchase_invoice_line', $lineCopy);
            $db->insert('purchase_invoice_line_withholding', ['id' => random_bytes(16), 'line_id' => $lineCopy['id']] + $line);
        }

        $this->client->request('GET', '/api/v1/reports/taxes/withholdings/practiced/export?format=pdf&check=1');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('export_too_large', $this->body()['error']);
        $this->client->request('GET', '/api/v1/reports/taxes/withholdings/practiced/export?format=pdf');
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/v1/reports/taxes/withholdings/practiced/export?format=csv&check=1');
        self::assertResponseStatusCodeSame(204, 'The CSV of the same report is well under its cap.');
    }
}
