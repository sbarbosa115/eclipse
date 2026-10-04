<?php

namespace App\Tests\Functional\Sales;

use App\Sales\Application\Collection\InvoiceCollections;
use App\Sales\Application\Command\CreateDraftSalesInvoice;
use App\Sales\Application\Command\SalesInvoiceData;
use App\Sales\Application\Command\SalesInvoiceLineData;
use App\Sales\Application\Command\SalesInvoicePaymentData;
use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Sales\Domain\Error\ReceivableNotFound;
use App\Sales\Infrastructure\Persistence\DoctrineReceivableRepository;
use App\Sales\Infrastructure\Persistence\DoctrineSalesInvoiceRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What other Sales items call without touching the invoice's aggregate: the recibo de caja applies and gives back
 * amounts (item 11), and the cotización converts into a draft (item 10).
 */
final class InvoiceCollectionsTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /**
     * Runs as a receipt's handler would: in its transaction, flushed at the end.
     */
    private function collect(callable $work): void
    {
        $em = $this->em();
        $em->clear();
        $em->getConnection()->beginTransaction();
        try {
            $work(new InvoiceCollections(new DoctrineReceivableRepository($em), new DoctrineSalesInvoiceRepository($em)));
            $em->flush();
            $em->getConnection()->commit();
        } catch (\Throwable $e) {
            $em->getConnection()->rollBack();

            throw $e;
        } finally {
            $em->clear();
        }
    }

    public function testAReceiptPaysTheInvoiceAndAVoidedOneGivesItBack(): void
    {
        $invoice = $this->emitted([$this->cash('190000.00'), $this->credit('1000000.00')]);
        $receivable = Uuid::fromString($invoice['receivables'][0]['id']);

        $this->collect(fn (InvoiceCollections $c) => self::assertSame($invoice['id'], $c->apply($this->company, $receivable, Money::of('400000'))->toRfc4122(), 'It names the invoice for the allocation.'));
        $partly = $this->getJson('/api/v1/sales-invoices/'.$invoice['id']);
        self::assertSame(['partially_paid', '400000.00', '600000.00', '600000.00'], [$partly['status'], $partly['paid_amount'], $partly['balance'], $partly['receivables'][0]['balance']]);

        $this->collect(fn (InvoiceCollections $c) => $c->apply($this->company, $receivable, Money::of('600000')));
        self::assertSame('paid', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status'], 'AC-4: paid.');

        $this->collect(fn (InvoiceCollections $c) => $c->unapply($this->company, $receivable, Money::of('600000')));
        self::assertSame('partially_paid', $this->getJson('/api/v1/sales-invoices/'.$invoice['id'])['status']);
    }

    public function testNoMoreThanTheBalance(): void
    {
        $invoice = $this->emitted([$this->credit('1190000.00')]);

        $this->expectException(AllocationExceedsBalance::class);
        $this->collect(fn (InvoiceCollections $c) => $c->apply($this->company, Uuid::fromString($invoice['receivables'][0]['id']), Money::of('1190000.01')));
    }

    public function testAnotherCompanysReceivableIsNotFound(): void
    {
        $invoice = $this->emitted([$this->credit('1190000.00')]);
        $this->signOut();
        $other = $this->startCompany('otra@acme.co', '901000001', 'Otra S.A.S.');

        $this->expectException(ReceivableNotFound::class);
        $this->collect(static fn (InvoiceCollections $c) => $c->apply($other, Uuid::fromString($invoice['receivables'][0]['id']), Money::of('1')));
    }

    public function testAQuotationConvertsIntoADraftThatRemembersIt(): void
    {
        $client = Uuid::fromString($this->client());
        $product = Uuid::fromString($this->service());
        $iva = Uuid::fromString($this->taxId('IVA 19 %'));
        $quotation = Uuid::v7();
        $owner = Uuid::fromString($this->getJson('/api/v1/me')['user_id']);

        $id = static::getContainer()->get(CommandBus::class)->dispatch(new CreateDraftSalesInvoice($this->company, $owner, new SalesInvoiceData(
            $client, null, null, new \DateTimeImmutable(self::today()), null,
            [new SalesInvoiceLineData($product, 'Consultoría', '1', '1000000', '0', $iva, null)],
            [new SalesInvoicePaymentData(Uuid::fromString($this->methodId('Crédito')), '1190000.00', new \DateTimeImmutable(self::today('+30 days')))],
        ), $quotation));

        $invoice = $this->getJson('/api/v1/sales-invoices/'.$id->toRfc4122());
        self::assertSame(['draft', $quotation->toRfc4122(), '1190000.00'], [$invoice['status'], $invoice['quotation_id'], $invoice['net_total']], '§4.7: the invoice records its origin.');
    }
}
