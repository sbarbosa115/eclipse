<?php

namespace App\Tests\Functional\Acceptance;

use App\Access\Domain\Model\Role;
use App\Tests\Functional\Sales\CashReceiptFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * AC-4: a cash receipt for the receivable (the 30-day half of AC-3's invoice) marks the invoice `pagado`, credits 1305
 * for exactly the amount, and the client's 1305 balance is zero.
 */
final class AC04CashReceiptPaysTheInvoiceTest extends ApiTestCase
{
    use CashReceiptFixtures;

    public function testTheReceiptForTheThirtyDayReceivablePaysTheInvoice(): void
    {
        // AC-3, as a billing user: half in cash, half at 30 days.
        $this->startCompany();
        $this->signInAs(Role::Billing);
        $client = $this->client('Cliente Inline S.A.S.');
        $invoice = $this->emitted([$this->cash('595000.00'), $this->credit('595000.00', self::today('+30 days'))], $client);
        self::assertSame('595000.00', $this->clientesBalance($client), 'Before: the client owes the crédito half in 1305.');

        $open = $this->getJson('/api/v1/cash-receipts/open-receivables?tercero_id='.$client)['items'];
        self::assertCount(1, $open, 'The one receivable, due in 30 days…');
        self::assertSame(self::today('+30 days'), $open[0]['due_date']);

        $receipt = $this->receive($client, '595000.00', [[$open[0]['id'], $open[0]['balance']]]);

        self::assertSame('paid', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status'], 'The invoice is pagado.');
        $entries = $this->entries();
        $entry = end($entries);
        self::assertSame('cash_receipt', $entry->sourceType());
        self::assertSame($receipt['number'], $entry->sourceNumber());
        $clientes = array_values(array_filter(self::movements($entry), static fn (array $m) => str_starts_with($m[0], '1305')));
        self::assertSame([['13050501', '0.00', '595000.00']], $clientes, '1305 is credited for exactly the amount.');
        self::assertTrue($entry->isBalanced());
        self::assertSame('0.00', $this->clientesBalance($client), 'The client’s 1305 balance is zero.');
        self::assertSame([], $this->getJson('/api/v1/cash-receipts/open-receivables?tercero_id='.$client)['items'], 'Nothing is open any more.');
    }
}
