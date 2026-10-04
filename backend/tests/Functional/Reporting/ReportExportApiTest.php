<?php

namespace App\Tests\Functional\Reporting;

use App\Reporting\Application\Export\ExportLimits;
use App\Tests\Support\ApiTestCase;

/**
 * Every report as CSV and PDF (§4.13, Q26): cartera de clientes and de proveedores, and the ledger's libro diario,
 * balance de prueba, estado de resultados and balance general.
 */
final class ReportExportApiTest extends ApiTestCase
{
    use ReportingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
        $client = $this->newClient('Ñandú; "Cía" S.A.S.');
        $this->sale($client, -10, -40);
        $this->bought($this->supplierNamed('Servicios Andinos S.A.S.'), 'A-1');
    }

    /** @return list<list<string>> */
    private function csv(string $uri): array
    {
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful($uri);
        $response = $this->client->getResponse();
        self::assertStringStartsWith('text/csv; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
        $content = $this->client->getInternalResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content, 'UTF-8 with a BOM.');

        return array_map(static fn (string $line) => array_map(static fn (?string $cell) => (string) $cell, str_getcsv($line, ';', '"', '')), explode("\r\n", rtrim(substr($content, 3), "\r\n")));
    }

    private function pdf(string $uri): string
    {
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful($uri);
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        $content = $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', (string) $content);

        return (string) $content;
    }

    public function testCarteraDeClientesCsv(): void
    {
        $rows = $this->csv('/api/v1/reports/cartera/clients/export?format=csv');

        self::assertSame(['Cliente', 'Identificación', 'Al día', '1-30 días', '31-60 días', '61-90 días', 'Más de 90 días', 'Total'], $rows[0]);
        self::assertSame(['Ñandú; "Cía" S.A.S.', 'NIT 800197268-4', '0.00', '1190000.00', '0.00', '0.00', '0.00', '1190000.00'], $rows[1] ?? [], 'A decimal point, quoted text, accents intact.');
        self::assertSame(['Total', '', '0.00', '1190000.00', '0.00', '0.00', '0.00', '1190000.00'], $rows[2], 'Totals row last.');
    }

    public function testCarteraDetailAndSearch(): void
    {
        $rows = $this->csv('/api/v1/reports/cartera/clients/export?detail=1');

        self::assertSame('Vencimiento', $rows[0][3]);
        self::assertSame([self::today(-10), '1190000.00'], [$rows[1][3], $rows[1][7]]);
        self::assertSame('days1_to30', $rows[1][5]);

        $none = $this->csv('/api/v1/reports/cartera/clients/export?q=zzz');
        self::assertCount(2, $none, 'Header and totals only.');
    }

    public function testCarteraDeProveedoresCsvAndPdf(): void
    {
        $rows = $this->csv('/api/v1/reports/cartera/suppliers/export?format=csv');
        self::assertSame('Proveedor', $rows[0][0]);
        self::assertSame('Servicios Andinos S.A.S.', $rows[1][0]);
        self::assertSame('1150000.00', $rows[1][7]);

        $pdf = $this->pdf('/api/v1/reports/cartera/suppliers/export?format=pdf');
        self::assertNotSame('', $pdf);
    }

    public function testTheLedgerBooksExportToCsv(): void
    {
        $journal = $this->csv('/api/v1/reports/ledger/journal/export');
        self::assertSame(['Fecha', 'Asiento', 'Documento', 'Cuenta', 'Nombre de la cuenta', 'Tercero', 'Descripción', 'Débito', 'Crédito'], $journal[0]);
        $total = end($journal);
        self::assertSame('Total', $total[0]);
        self::assertSame($total[7], $total[8], 'Σ débitos = Σ créditos.');
        self::assertGreaterThan(4, \count($journal));
        self::assertNotEmpty(array_filter(array_column($journal, 3), static fn (string $c) => str_starts_with($c, '1305')), 'The invoice debits 1305.');

        $filtered = $this->csv('/api/v1/reports/ledger/journal/export?account=1305&from='.self::today(-100).'&to='.self::today());
        self::assertLessThan(\count($journal), \count($filtered), 'The ledger’s own account filter leaves the purchase out (its entry has no 1305 line).');
        self::assertNotEmpty(array_filter(array_column($filtered, 3), static fn (string $c) => str_starts_with($c, '1305')));

        $trial = $this->csv('/api/v1/reports/ledger/trial-balance/export');
        self::assertSame(['Cuenta', 'Nombre', 'Saldo anterior', 'Débito', 'Crédito', 'Nuevo saldo'], $trial[0]);
        self::assertSame($trial[\count($trial) - 1][3], $trial[\count($trial) - 1][4], 'The trial balance balances.');

        $income = $this->csv('/api/v1/reports/ledger/income-statement/export');
        self::assertSame(['Cuenta', 'Nombre', 'Valor'], $income[0]);
        self::assertSame('Resultado del período', $income[\count($income) - 1][1]);

        $sheet = $this->csv('/api/v1/reports/ledger/balance-sheet/export?date='.self::today());
        self::assertSame('Total pasivo y patrimonio', $sheet[\count($sheet) - 1][1]);
    }

    public function testTheLedgerBooksExportToPdf(): void
    {
        foreach (['journal' => 'libro-diario', 'trial-balance' => 'balance-de-prueba', 'income-statement' => 'estado-de-resultados', 'balance-sheet' => 'balance-general'] as $report => $file) {
            $this->pdf("/api/v1/reports/ledger/$report/export?format=pdf");
            self::assertStringContainsString("filename=$file", (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        }
    }

    public function testThePdfCarriesTheCompanyFrame(): void
    {
        /** @var \Twig\Environment $twig */
        $twig = static::getContainer()->get('twig');
        $html = $twig->render('pdf/reports/table.html.twig', [
            'company' => static::getContainer()->get(\App\Company\Application\Query\Companies::class)->view($this->company),
            'title' => 'Cartera de clientes', 'subtitle' => ['Al 03/10/2026'],
            'columns' => [['label' => 'Cliente', 'numeric' => false], ['label' => 'Total', 'numeric' => true]],
            'rows' => [['Ana', '$ 1.190.000,00']], 'totals' => ['Total', '$ 1.190.000,00'],
        ]);

        self::assertStringContainsString('Acme S.A.S.', $html);
        self::assertStringContainsString('NIT 900123456', $html);
        self::assertStringContainsString('Cartera de clientes', $html);
        self::assertStringContainsString('$ 1.190.000,00', $html);
    }

    public function testBooksExportsAreForWhoMayViewTheBooks(): void
    {
        $this->signInAs('billing');
        $this->client->request('GET', '/api/v1/reports/ledger/journal/export');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/api/v1/reports/cartera/clients/export');
        self::assertResponseIsSuccessful('Billing exports cartera.');

        $this->signInAs('accountant');
        $this->client->request('GET', '/api/v1/reports/ledger/trial-balance/export');
        self::assertResponseIsSuccessful('The accountant exports the books.');

        $this->signOut();
        $this->client->request('GET', '/api/v1/reports/cartera/clients/export');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnotherCompanysFileHoldsNothingOfOurs(): void
    {
        $this->signOut();
        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');

        $rows = $this->csv('/api/v1/reports/cartera/clients/export');
        self::assertCount(2, $rows, 'Header and totals only.');
        self::assertCount(2, $this->csv('/api/v1/reports/ledger/journal/export'));
    }

    public function testABadFormatOrDateIsRefused(): void
    {
        $this->client->request('GET', '/api/v1/reports/cartera/clients/export?format=xlsx');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/api/v1/reports/ledger/trial-balance/export?from=ayer');
        self::assertResponseStatusCodeSame(400);
    }

    public function testAReportAboveTheCapIsRefusedNotCutShort(): void
    {
        // A libro diario of more entries than half the PDF cap: refused up front with the way out in the message.
        $entries = intdiv(ExportLimits::PDF_ROWS, 2) + 1;
        $db = $this->db();
        $user = $db->fetchOne('SELECT id FROM app_user WHERE company_id = ? LIMIT 1', [$this->company->toBinary()]);
        $number = (int) $db->fetchOne('SELECT MAX(number) FROM journal_entry WHERE company_id = ?', [$this->company->toBinary()]);
        for ($i = 1; $i <= $entries; ++$i) {
            $db->insert('journal_entry', [
                'id' => random_bytes(16), 'company_id' => $this->company->toBinary(), 'number' => $number + $i, 'entry_date' => self::today(),
                'source_type' => 'test', 'source_id' => random_bytes(16), 'source_number' => "T-$i", 'description' => 'Relleno',
                'created_by' => $user, 'created_at' => '2026-10-03 12:00:00',
            ]);
        }

        $this->client->request('GET', '/api/v1/reports/ledger/journal/export?format=pdf');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('export_too_large', $this->body()['error']);
        self::assertStringContainsString('Narrow', $this->body()['message']);

        $this->client->request('GET', '/api/v1/reports/ledger/journal/export?format=pdf&check=1');
        self::assertResponseStatusCodeSame(422, 'The rehearsal the screen makes refuses it too.');

        $this->csv('/api/v1/reports/ledger/journal/export?from='.self::today());
        self::assertResponseIsSuccessful('A CSV of the same size streams: its cap is far higher.');
    }

    public function testACheckRehearsesTheExportWithoutBuildingIt(): void
    {
        $this->client->request('GET', '/api/v1/reports/cartera/clients/export?format=pdf&check=1');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/api/v1/reports/ledger/journal/export?format=csv&check=1');
        self::assertResponseStatusCodeSame(204);

        $this->signInAs('billing');
        $this->client->request('GET', '/api/v1/reports/ledger/journal/export?check=1');
        self::assertResponseStatusCodeSame(403, 'A rehearsal is not a way around the permission.');
    }
}
