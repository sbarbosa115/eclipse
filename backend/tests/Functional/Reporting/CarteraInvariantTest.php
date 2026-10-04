<?php

namespace App\Tests\Functional\Reporting;

use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §5 invariant 3 from the reporting side: cartera de clientes equals the 1305 balance of the books and cartera de
 * proveedores the 2205 balance, as of any date and for each tercero, after a story of invoices, receipts, payments and
 * voids of both. (The documents' side of the invariant is in Sales\ClientBalanceInvariantTest and
 * Purchasing\SupplierBalanceInvariantTest.).
 */
final class CarteraInvariantTest extends ApiTestCase
{
    use ReportingFixtures;

    public function testTheCarteraTotalsAreTheLedgerBalancesAtEveryDate(): void
    {
        $this->startReporting();
        $ana = $this->newClient('Ana Ltda.', '800197268');
        $beto = $this->newClient('Beto S.A.S.', '900200300');
        // Beto's receivable account is his own, still under 1305.
        $this->db()->update('tercero', ['receivable_account_id' => $this->account('130510')->toBinary()], ['id' => Uuid::fromString($beto)->toBinary()]);
        $andes = $this->supplierNamed('Andes S.A.S.');
        $zeta = $this->supplierNamed('Zeta Ltda.');

        $first = $this->sale($ana, -10, -40);
        $this->sale($ana, 20, -30, '500000.00');
        $this->sale($beto, 5, -15);
        $voided = $this->sale($beto, 30, -5);
        $this->collect($ana, '300000.00', [[$first['receivables'][0]['id'], '300000.00']], 20);
        $mine = $this->bought($andes, 'A-1', -5, -35);
        $this->bought($zeta, 'Z-1', 10, -10);
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$voided['id'].'/void', ['reason' => 'Error de digitación']);
        self::assertResponseIsSuccessful();
        $receipt = $this->collect($beto, '400000.00', [[$this->getJson('/api/v1/cash-receipts/open-receivables?tercero_id='.$beto)['items'][0]['id'], '400000.00']]);
        $payment = $this->pay($andes, '500000.00', [[$mine['payables'][0]['id'], '500000.00']]);

        $this->assertInvariantAt('after the story');

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$receipt['id'].'/void', ['reason' => 'Cheque devuelto']);
        self::assertResponseIsSuccessful();
        $this->sendJson('POST', '/api/v1/supplier-payments/'.$payment['id'].'/void', ['reason' => 'Transferencia rechazada']);
        self::assertResponseIsSuccessful();
        $this->assertInvariantAt('after voiding a receipt and a payment');

        foreach ([-1, -5, -12, -18, -25, -32, -38, -50] as $days) {
            $this->assertInvariantAt("as of $days days", self::today($days));
        }

        // Per tercero: each row is that tercero's balance in the books.
        foreach ([$ana, $beto] as $client) {
            self::assertSame($this->terceroBalance('1305', $client), $this->rowTotal('clients', $client), 'Client balance in 1305.');
        }
        foreach ([$andes, $zeta] as $supplier) {
            self::assertSame($this->terceroBalance('2205', $supplier->toRfc4122(), true), $this->rowTotal('suppliers', $supplier->toRfc4122()), 'Supplier balance in 2205.');
        }
    }

    private function assertInvariantAt(string $when, ?string $date = null): void
    {
        $query = null === $date ? '' : '?as_of='.$date;
        $clients = $this->getJson('/api/v1/reports/cartera/clients'.$query)['totals']['total'];
        $suppliers = $this->getJson('/api/v1/reports/cartera/suppliers'.$query)['totals']['total'];

        self::assertSame($this->ledgerBalance('1305', $date), $clients, "Cartera de clientes = 1305, $when.");
        self::assertSame(Money::of($this->ledgerBalance('2205', $date))->negated()->toString(), $suppliers, "Cartera de proveedores = 2205, $when.");
        // The same figure from the ledger's own report, the one the accountant reads.
        $book = $this->getJson('/api/v1/ledger/trial-balance?to='.($date ?? self::today()).'&from=2000-01-01');
        $closing = static fn (string $code): string => array_values(array_filter($book['rows'], static fn (array $r) => $r['code'] === $code))[0]['closing'] ?? '0.00';
        self::assertSame($closing('1305'), $clients, "Cartera de clientes = the balance de prueba's 1305, $when.");
        self::assertSame(Money::of($closing('2205'))->negated()->toString(), $suppliers, "Cartera de proveedores = the balance de prueba's 2205, $when.");
    }

    private function rowTotal(string $side, string $tercero): string
    {
        foreach ($this->getJson("/api/v1/reports/cartera/$side")['items'] as $row) {
            if ($row['tercero_id'] === $tercero) {
                return $row['total'];
            }
        }

        return '0.00';
    }

    private function terceroBalance(string $prefix, string $tercero, bool $credit = false): string
    {
        $balance = $this->db()->fetchOne(
            \sprintf('SELECT COALESCE(SUM(%s), 0) FROM journal_line WHERE company_id = ? AND account_code LIKE ? AND tercero_id = ?', $credit ? 'credit - debit' : 'debit - credit'),
            [$this->company->toBinary(), $prefix.'%', Uuid::fromString($tercero)->toBinary()],
        );

        return Money::of((string) $balance)->toString();
    }
}
