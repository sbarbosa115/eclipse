<?php

namespace App\Tests\Functional\Reporting;

use App\Shared\Domain\Money\Money;
use App\Tests\Functional\Purchasing\SupplierPaymentFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * What the reporting tests share: the purchasing side's fixtures (a company, suppliers, payables, payments) plus a lean
 * sales side made through the API (a resolution, clients, invoices on crédito, receipts), so one company has both.
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait ReportingFixtures
{
    use SupplierPaymentFixtures;

    private ?string $salesProduct = null;

    protected function startReporting(): void
    {
        $this->startPurchasing();
        $this->sendJson('POST', '/api/v1/company/resolution', [
            'resolution_number' => '18764000001234',
            'prefix' => 'FE',
            'range_from' => 1,
            'range_to' => 1000,
            'valid_from' => self::today(-400),
            'valid_to' => self::today(400),
            'mode' => 'electronic',
        ]);
        self::assertResponseStatusCodeSame(201, 'The owner records the invoicing resolution.');
    }

    protected function newClient(string $name = 'Cliente Uno S.A.S.', string $nit = '800197268'): string
    {
        $tercero = $this->sendJson('POST', '/api/v1/terceros/quick', [
            'person_type' => 'empresa',
            'identification_type' => 'nit',
            'identification_number' => $nit,
            'business_name' => $name,
            'email' => 'facturas@cliente.co',
            'roles' => ['cliente'],
        ]);
        self::assertResponseStatusCodeSame(201, 'A client is created inline.');

        return $tercero['id'];
    }

    /**
     * An emitted sales invoice of 1.190.000 (1.000.000 + IVA 19 %) with the given crédito part due on a day, the rest in
     * cash; issued on the given day.
     *
     * @return array<string, mixed> the SalesInvoiceOutput
     */
    protected function sale(string $client, int $due = 30, int $issued = 0, string $credit = '1190000.00'): array
    {
        $this->salesProduct ??= $this->sendJson('POST', '/api/v1/products/quick', [
            'type' => 'servicio', 'code' => 'CONS', 'name' => 'Consultoría', 'sale_price' => '1000000',
            'price_includes_tax' => false, 'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => null,
        ])['id'];
        $payments = [['payment_method_id' => $this->methodId('Crédito'), 'amount' => $credit, 'due_date' => self::today($due)]];
        if ('1190000.00' !== $credit) {
            $payments[] = ['payment_method_id' => $this->methodId('Efectivo'), 'amount' => Money::of('1190000.00')->minus(Money::of($credit))->toString(), 'due_date' => null];
        }
        $draft = $this->sendJson('POST', '/api/v1/sales-invoices', [
            'tercero_id' => $client, 'contact_id' => null, 'seller_id' => null, 'issue_date' => self::today($issued), 'notes' => null,
            'lines' => [[
                'product_id' => $this->salesProduct, 'description' => 'Consultoría', 'quantity' => '1', 'unit_price' => '1000000',
                'discount' => '', 'charge_tax_id' => $this->taxId('IVA 19 %'), 'withholding_tax_id' => null,
            ]],
            'payments' => $payments,
        ]);
        self::assertResponseStatusCodeSame(201, 'The draft is saved: '.json_encode($draft));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('Emitted: '.json_encode($invoice));

        return $invoice;
    }

    /**
     * @param list<array{0: string, 1: string}> $allocations receivable id, amount
     *
     * @return array<string, mixed> the CashReceiptOutput
     */
    protected function collect(string $client, string $amount, array $allocations, int $daysAgo = 0): array
    {
        $receipt = $this->sendJson('POST', '/api/v1/cash-receipts', [
            'tercero_id' => $client, 'receipt_date' => self::today(-$daysAgo), 'payment_method_id' => $this->methodId('Efectivo'),
            'amount' => $amount, 'notes' => null,
            'allocations' => array_map(static fn (array $a) => ['receivable_id' => $a[0], 'amount' => $a[1]], $allocations),
        ]);
        self::assertResponseStatusCodeSame(201, 'The receipt is emitted: '.json_encode($receipt));

        return $receipt;
    }

    /**
     * An emitted purchase invoice of 1.150.000 on credit from the supplier (AC-5), issued and due on the given days.
     *
     * @return array<mixed> the PurchaseInvoiceOutput
     */
    protected function bought(Uuid $supplier, string $number, int $due = 30, int $issued = -1): array
    {
        return $this->owed($supplier, $number, [
            'issue_date' => self::today($issued),
            'due_date' => self::today($due),
            'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1150000.00', 'due_date' => self::today($due)]],
        ]);
    }

    /** What the books hold in an account family (débito − crédito) up to a day. */
    protected function ledgerBalance(string $prefix, ?string $asOf = null): string
    {
        $balance = $this->db()->fetchOne(
            'SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM journal_line l JOIN journal_entry e ON e.id = l.entry_id
             WHERE l.company_id = ? AND l.account_code LIKE ? AND e.entry_date <= ?',
            [$this->company->toBinary(), $prefix.'%', $asOf ?? self::today()],
        );

        return Money::of((string) $balance)->toString();
    }
}
