<?php

namespace App\Tests\Functional\Acceptance;

use App\Access\Domain\Model\Role;
use App\Sales\Domain\Model\Receivable;
use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * AC-3: a billing user creates a client and a service inline, emits an invoice paid half in cash and half at 30 days;
 * the journal shows one balanced entry debiting caja and 1305 and crediting ingresos and 2408; cartera shows one
 * receivable due in 30 days.
 */
final class AC03InvoiceHalfCashHalfCreditTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    public function testHalfInCashAndHalfAtThirtyDays(): void
    {
        $this->startCompany();
        $this->signInAs(Role::Billing);
        $client = $this->client('Cliente Inline S.A.S.');
        $service = $this->service('ASESORIA', '1000000');

        $draft = $this->draft($this->payload($client, $service, [
            'payments' => [$this->cash('595000.00'), $this->credit('595000.00', self::today('+30 days'))],
        ]));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful(json_encode($invoice) ?: '');

        $entries = $this->entries();
        self::assertCount(1, $entries, 'One entry.');
        self::assertSame([
            ['11050501', '595000.00', '0.00'],
            ['13050501', '595000.00', '0.00'],
            ['413595', '0.00', '1000000.00'],
            ['240805', '0.00', '190000.00'],
        ], self::movements($entries[0]), 'Débito caja and 1305; crédito ingresos and 2408.');
        self::assertTrue($entries[0]->totalDebit()->equals($entries[0]->totalCredit()), 'Balanced.');

        $this->em()->clear();
        $receivables = $this->em()->getRepository(Receivable::class)->findBy(['companyId' => $this->company]);
        self::assertCount(1, $receivables, 'Cartera shows one receivable…');
        self::assertSame(self::today('+30 days'), $receivables[0]->dueDate()->format('Y-m-d'), '…due in 30 days…');
        self::assertSame('595000.00', $receivables[0]->balance()->toString(), '…for the crédito half.');
        self::assertTrue($receivables[0]->isOpen());
        self::assertSame('emitted', $invoice['status']);
    }
}
