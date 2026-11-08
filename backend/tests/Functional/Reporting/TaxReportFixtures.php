<?php

namespace App\Tests\Functional\Reporting;

use App\Tests\Functional\Observations\WithholdingTaxes;
use Symfony\Component\Uid\Uuid;

/**
 * What the tax report tests share: one company with sales and purchases (ReportingFixtures), and invoices of
 * $1.000.000 + IVA carrying any retenciones, emitted on a chosen day. Amounts are written out in each test, so the
 * figures a report must show are read in the test itself.
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait TaxReportFixtures
{
    use ReportingFixtures;
    use WithholdingTaxes;

    private int $purchases = 0;

    /** The first day of this month (0) or of an earlier one (-1, -2…), in Colombia. */
    protected static function monthStart(int $months = 0): string
    {
        return (new \DateTimeImmutable('first day of this month', new \DateTimeZone('America/Bogota')))->modify(\sprintf('%+d months', $months))->format('Y-m-d');
    }

    protected static function month(int $months = 0): string
    {
        return substr(self::monthStart($months), 0, 7);
    }

    /**
     * An emitted factura de compra: 1.000.000 on 513595 with IVA (19 % by default) and these retenciones, on credit.
     *
     * @param list<string> $withholdings tax ids
     *
     * @return array<string, mixed> the PurchaseInvoiceOutput
     */
    protected function purchaseWith(array $withholdings, string $net, string $issued, ?Uuid $supplier = null, string $vat = 'IVA 19 %'): array
    {
        $line = $this->payload()['lines'][0];
        unset($line['withholding_tax_id']);
        $line['charge_tax_id'] = $this->taxId($vat);
        $line['withholdings'] = self::withholdings(...$withholdings);
        $invoice = $this->emitInvoice([
            'tercero_id' => ($supplier ?? $this->supplier)->toRfc4122(),
            'supplier_invoice_number' => 'FAC-'.(++$this->purchases),
            'issue_date' => $issued,
            'lines' => [$line],
            'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => $net, 'due_date' => self::today(30)]],
        ]);
        self::assertSame($net, $invoice['net_total'], 'The purchase nets what the test expects.');

        return $invoice;
    }

    /**
     * An emitted factura de venta to the client: one service of 1.000.000 with IVA (19 % by default) and these
     * retenciones, on credit.
     *
     * @param list<string> $withholdings tax ids
     *
     * @return array<string, mixed> the SalesInvoiceOutput
     */
    protected function saleWith(string $client, array $withholdings, string $net, string $issued, string $vat = 'IVA 19 %'): array
    {
        $this->salesProduct ??= $this->sendJson('POST', '/api/v1/products/quick', [
            'type' => 'servicio', 'code' => 'CONS', 'name' => 'Consultoría', 'sale_price' => '1000000',
            'price_includes_tax' => false, 'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => null,
        ])['id'];
        $draft = $this->sendJson('POST', '/api/v1/sales-invoices', [
            'tercero_id' => $client, 'contact_id' => null, 'seller_id' => null, 'issue_date' => $issued, 'notes' => null,
            'lines' => [[
                'product_id' => $this->salesProduct, 'description' => 'Consultoría', 'quantity' => '1', 'unit_price' => '1000000',
                'discount' => '', 'charge_tax_id' => $this->taxId($vat), 'withholdings' => self::withholdings(...$withholdings),
            ]],
            'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => $net, 'due_date' => self::today(30)]],
        ]);
        self::assertResponseStatusCodeSame(201, 'The draft is saved: '.json_encode($draft));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('Emitted: '.json_encode($invoice));
        self::assertSame($net, $invoice['net_total'], 'The sale nets what the test expects.');

        return $invoice;
    }

    /** Voids an invoice today (its reversing entry is dated today): 'purchase-invoices' or 'sales-invoices'. */
    protected function voidInvoice(string $resource, string $id): void
    {
        $body = $this->sendJson('POST', "/api/v1/$resource/$id/void", ['reason' => 'Error en la factura']);
        self::assertResponseIsSuccessful('Voided: '.json_encode($body));
    }

    protected function retefuente(): string
    {
        return $this->taxId('ReteFuente compras 2,5 %', 'withholding');
    }

    protected function reteIva(): string
    {
        return $this->taxId('ReteIVA 15 %', 'withholding');
    }
}
