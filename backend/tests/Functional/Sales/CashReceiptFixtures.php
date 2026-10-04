<?php

namespace App\Tests\Functional\Sales;

use App\Ledger\Domain\Model\JournalEntry;
use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What the recibo de caja tests share, on top of the sales invoice fixtures: invoices on crédito for a client, and a
 * receipt made through the API.
 *
 * @mixin ApiTestCase
 */
trait CashReceiptFixtures
{
    use SalesInvoiceFixtures;

    /**
     * An emitted invoice of 1.190.000 for the client, all on crédito (one receivable) unless payments are given.
     *
     * @param list<array<string, mixed>>|null $payments
     *
     * @return array<string, mixed> the SalesInvoiceOutput
     */
    protected function onCredit(string $client, ?array $payments = null, ?string $due = null): array
    {
        return $this->emitted($payments ?? [$this->credit('1190000.00', $due)], $client);
    }

    /**
     * @param list<array{0: string, 1: string}> $allocations receivable id, amount
     * @param array<string, mixed>              $over
     *
     * @return array<string, mixed>
     */
    protected function receiptPayload(string $client, string $amount, array $allocations, array $over = []): array
    {
        return $over + [
            'tercero_id' => $client,
            'receipt_date' => self::today(),
            'payment_method_id' => $this->methodId('Efectivo'),
            'amount' => $amount,
            'notes' => null,
            'allocations' => array_map(static fn (array $a) => ['receivable_id' => $a[0], 'amount' => $a[1]], $allocations),
        ];
    }

    /**
     * @param list<array{0: string, 1: string}> $allocations
     * @param array<string, mixed>              $over
     *
     * @return array<string, mixed> the CashReceiptOutput
     */
    protected function receive(string $client, string $amount, array $allocations, array $over = []): array
    {
        $receipt = $this->sendJson('POST', '/api/v1/cash-receipts', $this->receiptPayload($client, $amount, $allocations, $over));
        self::assertResponseStatusCodeSame(201, 'The receipt is emitted: '.json_encode($receipt));

        return $receipt;
    }

    /** The newest entry of the books. */
    protected function lastEntry(): JournalEntry
    {
        $entries = $this->entries();
        $last = end($entries);
        self::assertInstanceOf(JournalEntry::class, $last, 'The books have an entry.');

        return $last;
    }

    /**
     * The client's balance in 1305 (débito − crédito) from the books, every entry included.
     */
    protected function clientesBalance(string $client): string
    {
        $balance = $this->em()->getConnection()->fetchOne(
            "SELECT COALESCE(SUM(debit - credit), 0) FROM journal_line WHERE company_id = ? AND account_code LIKE '1305%' AND tercero_id = ?",
            [$this->company->toBinary(), Uuid::fromString($client)->toBinary()],
        );

        return Money::of((string) $balance)->toString();
    }
}
