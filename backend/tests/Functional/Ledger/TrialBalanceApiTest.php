<?php

namespace App\Tests\Functional\Ledger;

use App\Shared\Domain\Accounting\PostingConcept as C;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §4.13 Libro diario and balance de prueba, and the two basic statements derived from it (§9 Q25), over four entries:
 *
 *   15/09 cash sale        Dr 11050501 1.190.000   Cr 413595 1.000.000, 240805 190.000
 *   05/10 credit sale      Dr 13050501   595.000   Cr 413595   500.000, 240805  95.000   (client)
 *   10/10 service bought   Dr 513595 200.000, 240810 38.000   Cr 22050501 238.000          (supplier)
 *   20/10 supplier paid    Dr 22050501   238.000   Cr 11050501   238.000                   (supplier)
 */
final class TrialBalanceApiTest extends ApiTestCase
{
    use LedgerFixtures;

    private Uuid $clientId;
    private Uuid $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
        $this->clientId = $this->tercero('Cliente Uno S.A.S.');
        $this->supplier = $this->tercero('Proveedor Uno S.A.S.');
        $caja = $this->account('11050501');
        $this->post([self::line('debit', '1190000.00', $caja), self::line('credit', '1000000.00', C::Revenue), self::line('credit', '190000.00', C::VatGenerated)], date: '2026-09-15', number: 'FE-1');
        $this->post([self::line('debit', '595000.00', C::Receivables, $this->clientId), self::line('credit', '500000.00', C::Revenue), self::line('credit', '95000.00', C::VatGenerated)], date: '2026-10-05', number: 'FE-2');
        $this->post([self::line('debit', '200000.00', $this->account('513595')), self::line('debit', '38000.00', C::VatDeductible), self::line('credit', '238000.00', C::Payables, $this->supplier)], date: '2026-10-10', number: 'FC-1', type: 'purchase_invoice');
        $this->post([self::line('debit', '238000.00', C::Payables, $this->supplier), self::line('credit', '238000.00', $caja)], date: '2026-10-20', number: 'RP-1', type: 'supplier_payment');
    }

    /** @return array<array-key, array<string, mixed>> rows by code */
    private function trialBalance(string $query = 'from=2026-10-01&to=2026-10-31'): array
    {
        $balance = $this->getJson("/api/v1/ledger/trial-balance?$query");
        self::assertResponseIsSuccessful();

        return array_column($balance['rows'], null, 'code');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private static function amounts(array $row): array
    {
        return [$row['opening'], $row['debit'], $row['credit'], $row['closing']];
    }

    public function testEachAccountShowsItsOpeningMovementsAndClosingForThePeriod(): void
    {
        $rows = $this->trialBalance();

        self::assertSame(['1190000.00', '0.00', '238000.00', '952000.00'], self::amounts($rows['11050501']), 'Saldo anterior from September, then October.');
        self::assertSame(['0.00', '595000.00', '0.00', '595000.00'], self::amounts($rows['13050501']));
        self::assertSame(['-1000000.00', '0.00', '500000.00', '-1500000.00'], self::amounts($rows['413595']), 'Balances are débito minus crédito: a crédito balance is negative.');
        self::assertSame(['0.00', '238000.00', '238000.00', '0.00'], self::amounts($rows['22050501']), 'A cleared account still shows its movements.');
        self::assertSame(['CAJA GENERAL', 'auxiliary', 'debit'], [$rows['11050501']['name'], $rows['11050501']['level'], $rows['11050501']['nature']]);
    }

    public function testParentAccountsAddUpTheirChildren(): void
    {
        $rows = $this->trialBalance();

        self::assertSame(['1190000.00', '595000.00', '238000.00', '1547000.00'], self::amounts($rows['1']), 'Class 1, ACTIVO.');
        self::assertSame(['-190000.00', '38000.00', '95000.00', '-247000.00'], self::amounts($rows['2408']), 'IVA generado and descontable under 2408.');
        self::assertSame(['class', 'ACTIVO'], [$rows['1']['level'], $rows['1']['name']]);
        self::assertArrayNotHasKey('1110', $rows, 'Accounts without balance or movement are left out.');
        $codes = array_map(strval(...), array_keys($rows));
        $sorted = $codes;
        sort($sorted, \SORT_STRING);
        self::assertSame($sorted, $codes, 'Rows in code order.');
    }

    public function testDebitsEqualCredits(): void
    {
        $balance = $this->getJson('/api/v1/ledger/trial-balance?from=2026-10-01&to=2026-10-31');

        self::assertSame(['2026-10-01', '2026-10-31', '1071000.00', '1071000.00', true], [$balance['from'], $balance['to'], $balance['total_debit'], $balance['total_credit'], $balance['balanced']], '§4.13: Σ débitos = Σ créditos always.');
    }

    public function testTheLibroDiarioListsEntriesWithTheirSource(): void
    {
        $page = $this->getJson('/api/v1/ledger/journal?from=2026-10-01&to=2026-10-31');

        self::assertResponseIsSuccessful();
        self::assertSame(3, $page['total']);
        self::assertSame([2, 3, 4], array_column($page['items'], 'number'), 'By date, then number.');
        $sale = $page['items'][0];
        self::assertSame(['2026-10-05', 'sales_invoice', 'FE-2', 'Documento FE-2', null, '595000.00', '595000.00'], [$sale['date'], $sale['source_type'], $sale['source_number'], $sale['description'], $sale['reverses_id'], $sale['total_debit'], $sale['total_credit']]);
        self::assertSame([
            'account_id' => $this->account('13050501')->toRfc4122(),
            'account_code' => '13050501',
            'account_name' => 'CLIENTES NACIONALES',
            'tercero_id' => $this->clientId->toRfc4122(),
            'tercero_name' => 'Cliente Uno S.A.S.',
            'debit' => '595000.00',
            'credit' => '0.00',
            'description' => null,
        ], $sale['lines'][0]);
        self::assertNull($sale['lines'][1]['tercero_name']);
    }

    public function testTheLibroDiarioFiltersByAccountTerceroAndPages(): void
    {
        $byAccount = $this->getJson('/api/v1/ledger/journal?from=2026-10-01&to=2026-10-31&account=2408');
        self::assertSame(['FE-2', 'FC-1'], array_column($byAccount['items'], 'source_number'), 'A drill-down from 2408: every entry that moved it or its children.');
        self::assertCount(3, $byAccount['items'][0]['lines'], 'An entry shows all its lines.');

        $byTercero = $this->getJson('/api/v1/ledger/journal?tercero_id='.$this->supplier->toRfc4122());
        self::assertSame(['FC-1', 'RP-1'], array_column($byTercero['items'], 'source_number'));

        $paged = $this->getJson('/api/v1/ledger/journal?per_page=2&page=2&from=2026-01-01&to=2026-12-31');
        self::assertSame([4, 2, 2], [$paged['total'], $paged['page'], $paged['per_page']]);
        self::assertSame(['FC-1', 'RP-1'], array_column($paged['items'], 'source_number'));
    }

    public function testAVoidShowsAsItsOwnEntry(): void
    {
        $sale = $this->db()->fetchOne("SELECT id FROM journal_entry WHERE source_number = 'FE-2'");
        $this->reverse(Uuid::fromBinary((string) $sale), '2026-10-25');

        $page = $this->getJson('/api/v1/ledger/journal?from=2026-10-25&to=2026-10-25');
        self::assertSame(Uuid::fromBinary((string) $sale)->toRfc4122(), $page['items'][0]['reverses_id']);
        self::assertSame(['0.00', '595000.00', '595000.00', '0.00'], self::amounts($this->trialBalance()['13050501']));
    }

    public function testTheEstadoDeResultadosComesFromTheBalanceDePrueba(): void
    {
        $october = $this->getJson('/api/v1/ledger/income-statement?from=2026-10-01&to=2026-10-31');

        self::assertResponseIsSuccessful();
        self::assertSame(['500000.00', '0.00', '200000.00', '500000.00', '300000.00'], [$october['revenue'], $october['costs'], $october['expenses'], $october['gross_profit'], $october['net_income']]);
        self::assertSame(['4', '6', '7', '5'], array_column($october['sections'], 'code'), 'Ingresos, costos, then gastos.');
        $revenue = $october['sections'][0];
        self::assertSame(['INGRESOS', '500000.00'], [$revenue['name'], $revenue['total']]);
        self::assertSame([['41', 'OPERACIONALES', 'group', '500000.00'], ['4135', 'COMERCIO AL POR MAYOR Y AL POR MENOR', 'account', '500000.00']], array_map(static fn (array $l) => [$l['code'], $l['name'], $l['level'], $l['amount']], $revenue['lines']));

        $year = $this->getJson('/api/v1/ledger/income-statement?from=2026-01-01&to=2026-12-31');
        self::assertSame(['1500000.00', '1300000.00'], [$year['revenue'], $year['net_income']]);
    }

    public function testTheBalanceGeneralBalancesWithTheYearsResult(): void
    {
        $sheet = $this->getJson('/api/v1/ledger/balance-sheet?date=2026-10-31');

        self::assertResponseIsSuccessful();
        self::assertSame(['2026-10-31', '1547000.00', '247000.00', '1300000.00', '1300000.00', true], [$sheet['date'], $sheet['total_assets'], $sheet['total_liabilities'], $sheet['current_earnings'], $sheet['total_equity'], $sheet['balanced']], 'Activo = pasivo + patrimonio, with the unclosed result inside patrimonio.');
        self::assertSame(['1', '2', '3'], array_column($sheet['sections'], 'code'));
        self::assertSame('247000.00', $sheet['sections'][1]['total']);
        self::assertSame(['24', '2408'], array_column($sheet['sections'][1]['lines'], 'code'), '2205 is at zero and left out.');

        $september = $this->getJson('/api/v1/ledger/balance-sheet?date=2026-09-30');
        self::assertSame(['1190000.00', '190000.00', '1000000.00', true], [$september['total_assets'], $september['total_liabilities'], $september['current_earnings'], $september['balanced']]);
    }

    public function testAnotherCompanySeesNoneOfIt(): void
    {
        $this->signOut();
        $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');

        self::assertSame([], $this->getJson('/api/v1/ledger/trial-balance?from=2026-01-01&to=2026-12-31')['rows']);
        self::assertSame(0, $this->getJson('/api/v1/ledger/journal?from=2026-01-01&to=2026-12-31')['total']);
        self::assertSame('0.00', $this->getJson('/api/v1/ledger/income-statement?from=2026-01-01&to=2026-12-31')['revenue']);
        $this->getJson('/api/v1/ledger/journal?tercero_id='.$this->clientId->toRfc4122());
        self::assertSame(0, $this->body()['total'], 'Another company\'s tercero has no entries here.');
    }

    public function testABillingUserDoesNotReadTheBooks(): void
    {
        $this->signInAs('billing');

        foreach (['trial-balance', 'journal', 'income-statement', 'balance-sheet'] as $report) {
            $this->getJson("/api/v1/ledger/$report");
            self::assertResponseStatusCodeSame(403, "§8: $report is the owner's and the accountant's.");
        }
    }

    public function testTheAccountantReadsTheBooks(): void
    {
        $this->signInAs('accountant');

        $this->getJson('/api/v1/ledger/trial-balance');
        self::assertResponseIsSuccessful('Without dates: the year so far.');
    }

    public function testABadDateIsRefused(): void
    {
        $this->getJson('/api/v1/ledger/trial-balance?from=2026-13-01');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_date', $this->body()['error']);
    }
}
