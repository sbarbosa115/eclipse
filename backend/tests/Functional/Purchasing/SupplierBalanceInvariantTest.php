<?php

namespace App\Tests\Functional\Purchasing;

use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;

/**
 * §5 invariant 3, for a supplier: balance in 2205 = Σ emitted purchase invoices on crédito − Σ payments − Σ voided
 * amounts (voided invoices and voided payments both undo what they did), checked after every step of a story that does
 * all of it.
 */
final class SupplierBalanceInvariantTest extends ApiTestCase
{
    use SupplierPaymentFixtures;

    public function testTheSuppliersBalanceIn2205FollowsItsDocuments(): void
    {
        $this->startPurchasing();
        $expect = function (string $why): void {
            self::assertSame($this->fromDocuments()->toString(), $this->proveedoresBalance($this->supplier), $why);
        };

        $a = $this->owed(number: 'A-1');
        $b = $this->owed(number: 'A-2');
        $c = $this->owed(number: 'A-3');
        $cash = $this->emitInvoice(['supplier_invoice_number' => 'A-4', 'payments' => [['payment_method_id' => $this->methodId('Efectivo'), 'amount' => '1150000.00', 'due_date' => null]]]);
        self::assertSame([], $cash['payables'], 'An all-cash invoice creates no payable.');
        $expect('Invoices on crédito (an all-cash one adds nothing).');
        self::assertSame('3450000.00', $this->proveedoresBalance($this->supplier));

        $paysA = $this->pay($this->supplier, '1150000.00', [[$a['payables'][0]['id'], '1150000.00']]);
        $this->pay($this->supplier, '400000.00', [[$b['payables'][0]['id'], '300000.00'], [$c['payables'][0]['id'], '100000.00']]);
        $expect('After payments.');

        $this->sendJson('POST', '/api/v1/supplier-payments/'.$paysA['id'].'/void', ['reason' => 'Transferencia rechazada']);
        self::assertResponseIsSuccessful();
        $expect('After a voided payment.');

        $this->sendJson('POST', '/api/v1/purchase-invoices/'.$a['id'].'/void', ['reason' => 'Factura errada']);
        self::assertResponseIsSuccessful();
        $expect('After a voided invoice.');
        self::assertSame('1900000.00', $this->proveedoresBalance($this->supplier), 'b + c on crédito 2 300 000 − 400 000 paid; the voided a and its voided payment count nothing.');
    }

    /** Σ crédito of emitted invoices − Σ emitted payments, from the documents' own tables (voided ones count nothing). */
    private function fromDocuments(): Money
    {
        $id = $this->supplier->toBinary();
        $owed = $this->db()->fetchOne(
            "SELECT COALESCE(SUM(p.amount), 0) FROM purchase_invoice_payment p JOIN purchase_invoice i ON i.id = p.invoice_id
             WHERE i.company_id = ? AND i.tercero_id = ? AND i.status NOT IN ('draft', 'voided') AND p.kind = 'credit'",
            [$this->company->toBinary(), $id],
        );
        $paid = $this->db()->fetchOne("SELECT COALESCE(SUM(amount), 0) FROM supplier_payment WHERE company_id = ? AND tercero_id = ? AND status = 'emitted'", [$this->company->toBinary(), $id]);

        return Money::of((string) $owed)->minus(Money::of((string) $paid));
    }
}
