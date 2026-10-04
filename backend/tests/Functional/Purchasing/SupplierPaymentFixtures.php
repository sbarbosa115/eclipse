<?php

namespace App\Tests\Functional\Purchasing;

use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * What the recibo de pago tests share, on top of the purchase invoice fixtures: invoices on credit from a supplier
 * (1 150 000 each: the AC-5 service), and a payment made through the API.
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait SupplierPaymentFixtures
{
    use PurchasingFixtures;

    /** A supplier with its own e-mail, or none. */
    protected function supplierNamed(string $name, ?string $email = 'pagos@proveedor.co'): Uuid
    {
        $id = $this->tercero($name);
        $this->db()->update('tercero', ['email' => $email], ['id' => $id->toBinary()]);

        return $id;
    }

    /**
     * An emitted purchase invoice of 1 150 000 on credit from the supplier (AC-5).
     *
     * @param array<string, mixed> $over
     *
     * @return array<mixed> the PurchaseInvoiceOutput
     */
    protected function owed(?Uuid $supplier = null, string $number = 'FAC-881', array $over = []): array
    {
        return $this->emitInvoice($over + ['tercero_id' => ($supplier ?? $this->supplier)->toRfc4122(), 'supplier_invoice_number' => $number]);
    }

    /**
     * @param list<array{0: string, 1: string}> $allocations payable id, amount
     * @param array<string, mixed>              $over
     *
     * @return array<string, mixed>
     */
    protected function paymentPayload(Uuid|string $supplier, string $amount, array $allocations, array $over = []): array
    {
        return $over + [
            'tercero_id' => $supplier instanceof Uuid ? $supplier->toRfc4122() : $supplier,
            'receipt_date' => self::today(),
            'payment_method_id' => $this->methodId('Efectivo'),
            'amount' => $amount,
            'notes' => null,
            'allocations' => array_map(static fn (array $a) => ['payable_id' => $a[0], 'amount' => $a[1]], $allocations),
        ];
    }

    /**
     * @param list<array{0: string, 1: string}> $allocations
     * @param array<string, mixed>              $over
     *
     * @return array<string, mixed> the SupplierPaymentOutput
     */
    protected function pay(Uuid|string $supplier, string $amount, array $allocations, array $over = []): array
    {
        $payment = $this->sendJson('POST', '/api/v1/supplier-payments', $this->paymentPayload($supplier, $amount, $allocations, $over));
        self::assertResponseStatusCodeSame(201, 'The payment is emitted: '.json_encode($payment));

        return $payment;
    }

    /** @return array<mixed> */
    protected function purchase(string $id): array
    {
        return $this->getJson('/api/v1/purchase-invoices/'.$id);
    }

    /** The newest entry of the books. */
    protected function lastEntry(): \App\Ledger\Domain\Model\JournalEntry
    {
        $entries = $this->entries();
        $last = end($entries);
        self::assertInstanceOf(\App\Ledger\Domain\Model\JournalEntry::class, $last, 'The books have an entry.');

        return $last;
    }

    /** What the company owes the supplier in 2205 (crédito − débito) from the books, every entry included. */
    protected function proveedoresBalance(Uuid|string $supplier): string
    {
        $id = $supplier instanceof Uuid ? $supplier : Uuid::fromString($supplier);
        $balance = $this->db()->fetchOne(
            "SELECT COALESCE(SUM(credit - debit), 0) FROM journal_line WHERE company_id = ? AND account_code LIKE '2205%' AND tercero_id = ?",
            [$this->company->toBinary(), $id->toBinary()],
        );

        return Money::of((string) $balance)->toString();
    }
}
