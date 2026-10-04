<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Purchasing\SupplierPaymentFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §7, acceptance criterion 6: "A supplier payment clears that payable; the balance de prueba still balances." The
 * payable is AC-5's: a service with IVA 19 % and ReteFuente 4 % on credit.
 */
final class AC06SupplierPaymentClearsThePayableTest extends ApiTestCase
{
    use SupplierPaymentFixtures;

    public function testASupplierPaymentClearsTheServicePayable(): void
    {
        $this->startPurchasing();
        $invoice = $this->emitInvoice();
        self::assertSame('1150000.00', $this->proveedoresBalance($this->supplier), 'Before: 1 000 000 + 190 000 − 40 000 are owed in 2205 (AC-5).');

        $open = $this->getJson('/api/v1/supplier-payments/open-payables?tercero_id='.$this->supplier->toRfc4122())['items'];
        self::assertCount(1, $open);
        self::assertSame('1150000.00', $open[0]['balance']);

        $payment = $this->pay($this->supplier, '1150000.00', [[$open[0]['id'], $open[0]['balance']]]);

        $read = $this->purchase($invoice['id']);
        self::assertSame(['paid', '0.00', '1150000.00'], [$read['status'], $read['balance'], $read['paid_amount']], 'The purchase invoice is pagada.');
        self::assertSame('0.00', $read['payables'][0]['balance'], 'The payable is cleared…');
        self::assertSame([], $this->getJson('/api/v1/supplier-payments/open-payables?tercero_id='.$this->supplier->toRfc4122())['items'], '…and leaves cartera de proveedores.');

        $entry = $this->lastEntry();
        self::assertSame('supplier_payment', $entry->sourceType());
        self::assertSame($payment['number'], $entry->sourceNumber());
        self::assertTrue($entry->isBalanced());
        self::assertSame('0.00', $this->proveedoresBalance($this->supplier), 'The supplier’s 2205 balance is zero.');

        $trial = $this->getJson('/api/v1/ledger/trial-balance?from='.self::today(-30).'&to='.self::today(30));
        self::assertResponseIsSuccessful();
        self::assertTrue($trial['balanced'], 'The balance de prueba still balances.');
        self::assertSame($trial['total_debit'], $trial['total_credit']);
        $rows = array_column($trial['rows'], null, 'code');
        self::assertSame('0.00', $rows['22050501']['closing'], '2205 closes at zero.');
        self::assertSame('1150000.00', $rows['22050501']['debit']);
        self::assertSame('1150000.00', $rows['22050501']['credit']);
    }
}
