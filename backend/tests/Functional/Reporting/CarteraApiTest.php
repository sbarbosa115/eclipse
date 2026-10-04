<?php

namespace App\Tests\Functional\Reporting;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Cartera de clientes and de proveedores (§4.13) through the API: ageing buckets by due date, totals, drill-down,
 * search, pagination, "as of" a past date, voids and collections, roles and tenancy.
 */
final class CarteraApiTest extends ApiTestCase
{
    use ReportingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
    }

    /** @return array<string, mixed> */
    private function row(string $side, string $terceroId): array
    {
        $rows = array_values(array_filter($this->getJson("/api/v1/reports/cartera/$side")['items'], static fn (array $r) => $r['tercero_id'] === $terceroId));
        self::assertCount(1, $rows, 'The tercero has a row.');

        return $rows[0];
    }

    public function testClientsOwingByAgeingBucket(): void
    {
        $client = $this->newClient();
        // Issued 200 days ago, due: in 10 days, 5 days ago, 45, 75 and 120 days ago: one in each bucket.
        foreach ([10, -5, -45, -75, -120] as $due) {
            $this->sale($client, $due, -200);
        }

        $body = $this->getJson('/api/v1/reports/cartera/clients');

        self::assertResponseIsSuccessful();
        self::assertSame(self::today(), $body['as_of'], 'Default: today in Colombia.');
        self::assertCount(1, $body['items']);
        $row = $body['items'][0];
        self::assertSame($client, $row['tercero_id']);
        self::assertSame('Cliente Uno S.A.S.', $row['name']);
        self::assertSame('NIT 800197268-4', $row['identification']);
        foreach (['current', 'days1_to30', 'days31_to60', 'days61_to90', 'over90'] as $bucket) {
            self::assertSame('1190000.00', $row[$bucket], $bucket);
        }
        self::assertSame('5950000.00', $row['total']);
        self::assertSame('4760000.00', $row['overdue'], 'Everything but al día.');
        self::assertSame(5, $row['documents']);
        self::assertSame($row['total'], $body['totals']['total'], 'The grand total.');
        self::assertSame(['total' => 1, 'page' => 1], ['total' => $body['total'], 'page' => $body['page']]);
        self::assertSame(1, $body['totals']['terceros']);
    }

    public function testADrillDownListsTheOpenDocumentsSoonestDueFirst(): void
    {
        $client = $this->newClient();
        $late = $this->sale($client, -45, -100);
        $soon = $this->sale($client, 10, -20);

        $body = $this->getJson("/api/v1/reports/cartera/clients/$client");

        self::assertResponseIsSuccessful();
        self::assertSame('Cliente Uno S.A.S.', $body['tercero_name']);
        self::assertSame([$late['number'], $soon['number']], array_column($body['items'], 'invoice_number'));
        self::assertSame(['days31_to60', 'current'], array_column($body['items'], 'bucket'));
        self::assertSame([45, -10], array_column($body['items'], 'days_overdue'));
        self::assertSame($late['id'], $body['items'][0]['invoice_id'], 'A link to the invoice.');
        self::assertSame(['issue_date' => self::today(-100), 'due_date' => self::today(-45), 'amount' => '1190000.00', 'balance' => '1190000.00'], array_intersect_key($body['items'][0], array_flip(['issue_date', 'due_date', 'amount', 'balance'])));
        self::assertSame('2380000.00', $body['total']);
    }

    public function testACollectionReducesTheBalanceAndAVoidedOneRestoresIt(): void
    {
        $client = $this->newClient();
        $invoice = $this->sale($client, -5, -40);
        $receivable = $invoice['receivables'][0]['id'];

        $receipt = $this->collect($client, '190000.00', [[$receivable, '190000.00']]);
        self::assertSame('1000000.00', $this->row('clients', $client)['total']);

        $this->collect($client, '1000000.00', [[$receivable, '1000000.00']]);
        self::assertSame([], $this->getJson('/api/v1/reports/cartera/clients')['items'], 'Collected in full: no longer in cartera.');
        $this->getJson("/api/v1/reports/cartera/clients/$client");
        self::assertResponseStatusCodeSame(404, 'A tercero with nothing owed has no drill-down.');

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Cheque devuelto']);
        self::assertResponseIsSuccessful();
        self::assertSame('190000.00', $this->row('clients', $client)['total'], 'The voided receipt gives its 190.000 back.');
    }

    public function testAVoidedInvoiceIsNotCartera(): void
    {
        $client = $this->newClient();
        $invoice = $this->sale($client);
        $this->sale($client, 5);

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Error']);
        self::assertResponseIsSuccessful();

        self::assertSame('1190000.00', $this->row('clients', $client)['total']);
    }

    public function testAsOfAPastDateRebuildsWhatWasOwedThen(): void
    {
        $client = $this->newClient();
        $old = $this->sale($client, -50, -90);
        $this->sale($client, 20, -10);
        // The old invoice (issued 90 days ago, due 50 days ago) was collected 30 days ago.
        $this->collect($client, '1190000.00', [[$old['receivables'][0]['id'], '1190000.00']], 30);

        $now = $this->getJson('/api/v1/reports/cartera/clients');
        self::assertSame('1190000.00', $now['totals']['total'], 'Today only the recent invoice is open.');
        self::assertSame('1190000.00', $now['totals']['current']);

        $then = $this->getJson('/api/v1/reports/cartera/clients?as_of='.self::today(-60));
        self::assertSame(self::today(-60), $then['as_of']);
        self::assertSame('1190000.00', $then['totals']['total'], '60 days ago only the old invoice existed, whole.');
        self::assertSame('1190000.00', $then['totals']['current'], 'It was due 10 days later: al día then.');

        $late = $this->getJson('/api/v1/reports/cartera/clients?as_of='.self::today(-35));
        self::assertSame('1190000.00', $late['totals']['days1_to30'], '35 days ago it was 15 days late, not yet collected.');

        $between = $this->getJson('/api/v1/reports/cartera/clients?as_of='.self::today(-20));
        self::assertSame('0.00', $between['totals']['total'], '20 days ago: the old one was collected, the recent one not yet issued.');
        self::assertSame([], $between['items']);
    }

    public function testSuppliersByAgeingBucket(): void
    {
        $due = [10, -5, -45, -75, -120];
        $suppliers = [];
        foreach ($due as $i => $days) {
            $suppliers[$i] = $this->supplierNamed("Proveedor $i S.A.S.");
            $this->bought($suppliers[$i], "P-$i", $days, -200);
        }

        $body = $this->getJson('/api/v1/reports/cartera/suppliers');

        self::assertResponseIsSuccessful();
        self::assertSame('5750000.00', $body['totals']['total'], '5 × 1.150.000 (the payable is the net after ReteFuente).');
        self::assertSame('1150000.00', $body['totals']['current']);
        self::assertSame('1150000.00', $body['totals']['days1_to30']);
        self::assertSame('1150000.00', $body['totals']['days31_to60']);
        self::assertSame('1150000.00', $body['totals']['days61_to90']);
        self::assertSame('1150000.00', $body['totals']['over90']);
        self::assertSame('4600000.00', $body['totals']['overdue']);
        self::assertSame(5, $body['total']);

        $drill = $this->getJson('/api/v1/reports/cartera/suppliers/'.$suppliers[2]->toRfc4122());
        self::assertSame(['days31_to60'], array_column($drill['items'], 'bucket'));
    }

    public function testASupplierPaymentReducesItAndSearchAndPaginationWork(): void
    {
        $andes = $this->supplierNamed('Servicios Andinos S.A.S.');
        $zeta = $this->supplierNamed('Zeta Ltda.');
        $first = $this->bought($andes, 'A-1');
        $this->bought($zeta, 'Z-1');
        $this->bought($zeta, 'Z-2');
        $this->pay($andes, '150000.00', [[$first['payables'][0]['id'], '150000.00']]);

        $all = $this->getJson('/api/v1/reports/cartera/suppliers');
        self::assertSame(['Zeta Ltda.', 'Servicios Andinos S.A.S.'], array_column($all['items'], 'name'), 'Largest balance first.');
        self::assertSame('1000000.00', $this->row('suppliers', $andes->toRfc4122())['total']);

        $found = $this->getJson('/api/v1/reports/cartera/suppliers?q=andinos');
        self::assertSame(['Servicios Andinos S.A.S.'], array_column($found['items'], 'name'));
        self::assertSame('1000000.00', $found['totals']['total'], 'The grand total is of what matches.');
        self::assertSame([], $this->getJson('/api/v1/reports/cartera/suppliers?q=%25')['items'], 'A % is searched literally.');

        $page2 = $this->getJson('/api/v1/reports/cartera/suppliers?per_page=1&page=2');
        self::assertSame(['Servicios Andinos S.A.S.'], array_column($page2['items'], 'name'));
        self::assertSame(2, $page2['total']);
        self::assertSame('3300000.00', $page2['totals']['total'], 'Totals cover every page.');
    }

    public function testAnotherCompanyNeverSeesIt(): void
    {
        $client = $this->newClient();
        $this->sale($client);
        $this->signOut();
        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');

        self::assertSame([], $this->getJson('/api/v1/reports/cartera/clients')['items']);
        self::assertSame('0.00', $this->getJson('/api/v1/reports/cartera/clients')['totals']['total']);
        $this->getJson("/api/v1/reports/cartera/clients/$client");
        self::assertResponseStatusCodeSame(404);
    }

    public function testEveryRoleThatReadsDocumentsMayRead(): void
    {
        $this->sale($this->newClient());
        foreach (['billing', 'accountant'] as $role) {
            $this->signInAs($role);
            $this->getJson('/api/v1/reports/cartera/clients');
            self::assertResponseIsSuccessful("$role reads cartera.");
        }
        $this->signOut();
        $this->getJson('/api/v1/reports/cartera/clients');
        self::assertResponseStatusCodeSame(401);
    }

    public function testABadDateOrSideIsRefused(): void
    {
        $this->getJson('/api/v1/reports/cartera/clients?as_of=03-10-2026');
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_date', $this->body()['error']);
        $this->getJson('/api/v1/reports/cartera/everybody');
        self::assertResponseStatusCodeSame(404);
        $this->getJson('/api/v1/reports/cartera/clients/'.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
    }
}
