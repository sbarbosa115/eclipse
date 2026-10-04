<?php

namespace App\Tests\Functional\Acceptance;

use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Domain\Model\SalesInvoice;
use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * AC-7: voiding an unpaid invoice produces a reversing entry, status anulado, PDF marked ANULADA, and the number is
 * not reused.
 */
final class AC07VoidUnpaidInvoiceTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    public function testVoidingAnUnpaidInvoice(): void
    {
        $this->startCompany();
        $invoice = $this->emitted([$this->credit('1190000.00')]);

        $voided = $this->sendJson('POST', '/api/v1/sales-invoices/'.$invoice['id'].'/void', ['reason' => 'Factura duplicada']);

        self::assertResponseIsSuccessful();
        self::assertSame('voided', $voided['status'], 'Status anulado.');
        [$entry, $reversal] = $this->entries();
        self::assertTrue($entry->id()->equals($reversal->reversesId() ?? Uuid::v7()), 'A reversing entry…');
        self::assertTrue($entry->totalDebit()->equals($reversal->totalCredit()), '…that mirrors it.');

        $this->em()->clear();
        $model = $this->em()->find(SalesInvoice::class, Uuid::fromString($invoice['id']));
        \assert($model instanceof SalesInvoice);
        $html = static::getContainer()->get(Environment::class)->render(SalesInvoicePdf::TEMPLATE, static::getContainer()->get(SalesInvoicePdf::class)->context($model));
        self::assertStringContainsString('ANULADA', $html, 'PDF marked ANULADA.');

        $next = $this->emitted();
        self::assertSame(['FE-1', 'FE-2'], [$voided['number'], $next['number']], 'The number is not reused.');
    }
}
