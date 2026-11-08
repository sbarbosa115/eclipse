<?php

namespace App\Tests\Functional\Acceptance;

use App\Access\Domain\Model\Role;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Domain\Model\SalesInvoice;
use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Observations PRD §8, OBS-2: "A sales invoice line of $1.000.000 with IVA 19 %, Retefuente 2,5 % and ReteICA 0,966 % and
 * ReteIVA 15 %: Retenciones = 25.000 + 9.660 + 28.500 = 63.160; Total neto = 1.126.840; the entry has Dr 135515 25.000,
 * Dr 135518 9.660, Dr 135517 28.500; […] the PDF lists the three retenciones with their bases." (The editor's preview is
 * item 3's.).
 */
final class Obs02SalesInvoiceWithThreeRetencionesTest extends ApiTestCase
{
    use SalesInvoiceFixtures;
    use WithholdingTaxes;

    public function testEachRetencionIsPostedToItsOwnAccountAndPrintedWithItsBase(): void
    {
        $this->startCompany();
        $reteIca = $this->reteIca();
        $this->signInAs(Role::Billing);
        $client = $this->client();
        $service = $this->service('ASESORIA', '1000000');

        $draft = $this->draft($this->payload($client, $service, [
            'lines' => [$this->line($service, ['withholdings' => self::withholdings($this->taxId('ReteFuente compras 2,5 %'), $reteIca, $this->taxId('ReteIVA 15 %'))])],
            'payments' => [$this->credit('1126840.00')],
        ]));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful(json_encode($invoice) ?: '');

        self::assertSame(['1000000.00', '190000.00', '63160.00', '1126840.00'], [$invoice['subtotal'], $invoice['tax_total'], $invoice['withholding_total'], $invoice['net_total']], 'Retenciones = 25.000 + 9.660 + 28.500; Total neto = 1.126.840.');
        $entries = $this->entries();
        self::assertCount(1, $entries);
        self::assertSame([
            ['13050501', '1126840.00', '0.00'],
            ['135515', '25000.00', '0.00'],
            ['135518', '9660.00', '0.00'],
            ['135517', '28500.00', '0.00'],
            ['413595', '0.00', '1000000.00'],
            ['240805', '0.00', '190000.00'],
        ], self::movements($entries[0]), 'One debit per retención account: 135515, 135518, 135517.');
        self::assertTrue($entries[0]->totalDebit()->equals($entries[0]->totalCredit()), 'Balanced.');

        $this->em()->clear();
        $model = $this->em()->find(SalesInvoice::class, Uuid::fromString($invoice['id'])) ?? self::fail('No invoice.');
        $html = static::getContainer()->get('twig')->render(SalesInvoicePdf::TEMPLATE, static::getContainer()->get(SalesInvoicePdf::class)->context($model));
        foreach (['ReteFuente compras 2,5 % <span class="muted">(base $ 1.000.000,00)', 'ReteICA 0,966 % <span class="muted">(base $ 1.000.000,00)', 'ReteIVA 15 % <span class="muted">(base $ 190.000,00)'] as $row) {
            self::assertStringContainsString($row, $html, 'The PDF lists the three retenciones with their bases.');
        }
    }
}
