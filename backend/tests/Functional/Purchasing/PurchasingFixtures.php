<?php

namespace App\Tests\Functional\Purchasing;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Model\JournalEntry;
use App\Ledger\Domain\Model\JournalLine;
use App\Tests\Functional\Ledger\LedgerFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * What the purchase invoice tests share: a signed-up company (owner signed in) with the seeded chart, taxes and payment
 * methods, a supplier, and a factura de compra payload to start from.
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait PurchasingFixtures
{
    use LedgerFixtures;

    protected Uuid $supplier;

    protected function startPurchasing(string $email = 'ana@acme.co', string $nit = '900123456', string $name = 'Acme S.A.S.'): void
    {
        $this->startCompany($email, $nit, $name);
        $this->supplier = $this->tercero('Servicios Andinos S.A.S.');
    }

    /** Today in Colombia, as the API writes dates. */
    protected static function today(int $plusDays = 0): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->modify(\sprintf('%+d days', $plusDays))->format('Y-m-d');
    }

    protected function taxId(string $name, string $class = 'charge'): string
    {
        foreach (static::getContainer()->get(LedgerCatalog::class)->taxes($this->company, $class, false) as $tax) {
            if ($tax->name === $name) {
                return $tax->id;
            }
        }
        throw new \LogicException("No tax $name.");
    }

    /** Makes a tax in force only from a day on (Configuración › Impuestos), as the owner. */
    protected function taxInForceFrom(string $taxId, string $from): void
    {
        foreach ($this->getJson('/api/v1/taxes?all=1')['items'] as $tax) {
            if ($tax['id'] === $taxId) {
                $this->sendJson('PUT', "/api/v1/taxes/$taxId", ['name' => $tax['name'], 'calculation' => $tax['calculation'], 'rate' => $tax['rate'], 'sales_account_id' => $tax['sales_account_id'], 'purchase_account_id' => $tax['purchase_account_id'], 'valid_from' => $from, 'valid_to' => null]);
                self::assertResponseIsSuccessful('The tax now starts on '.$from);

                return;
            }
        }
        self::fail("No tax $taxId.");
    }

    protected function methodId(string $name): string
    {
        foreach (static::getContainer()->get(LedgerCatalog::class)->paymentMethods($this->company, false) as $method) {
            if ($method->name === $name) {
                return $method->id;
            }
        }
        throw new \LogicException("No payment method $name.");
    }

    /**
     * A service of 1 000 000 on 513595 with IVA 19 % and ReteFuente servicios 4 %, all on credit at 30 days: total neto
     * 1 150 000 (acceptance criterion 5).
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected function payload(array $override = []): array
    {
        return $override + [
            'tercero_id' => $this->supplier->toRfc4122(),
            'supplier_invoice_number' => 'FAC-881',
            'issue_date' => self::today(-1),
            'due_date' => self::today(29),
            'notes' => 'Mantenimiento de octubre',
            'lines' => [[
                'product_id' => null,
                'account_id' => $this->account('513595')->toRfc4122(),
                'description' => 'Mantenimiento de equipos',
                'quantity' => '1',
                'unit_price' => '1000000',
                'discount' => '0',
                'charge_tax_id' => $this->taxId('IVA 19 %'),
                'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %', 'withholding'),
            ]],
            'payments' => [[
                'payment_method_id' => $this->methodId('Crédito'),
                'amount' => '1150000.00',
                'due_date' => self::today(29),
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<mixed> the PurchaseInvoiceOutput
     */
    protected function createDraft(array $override = []): array
    {
        $body = $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload($override));
        self::assertResponseStatusCodeSame(201, 'The draft is saved: '.json_encode($body));

        return $body;
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<mixed> the emitted PurchaseInvoiceOutput
     */
    protected function emitInvoice(array $override = []): array
    {
        $draft = $this->createDraft($override);
        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);
        self::assertResponseIsSuccessful('The invoice is emitted: '.json_encode($body));

        return $body;
    }

    /**
     * @return list<array{string, string, string, ?string}> account code, débito, crédito, tercero
     */
    protected static function movements(JournalEntry $entry): array
    {
        return array_map(static fn (JournalLine $l) => [$l->accountCode(), $l->debit()->toString(), $l->credit()->toString(), $l->terceroId()?->toRfc4122()], $entry->lines());
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<string>
     */
    protected static function violationFields(array $body): array
    {
        return array_column($body['violations'] ?? [], 'field');
    }
}
