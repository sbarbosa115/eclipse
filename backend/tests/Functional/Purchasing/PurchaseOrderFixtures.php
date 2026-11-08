<?php

namespace App\Tests\Functional\Purchasing;

use Symfony\Component\Uid\Uuid;

/**
 * What the purchase order tests share, on top of the purchasing fixtures: a supplier with an e-mail, a product,
 * and drafts and emitted orders made through the API.
 *
 * @mixin \App\Tests\Support\ApiTestCase
 */
trait PurchaseOrderFixtures
{
    use PurchasingFixtures;

    /** A supplier with a billing e-mail (the one the order goes to). */
    protected function supplierWithEmail(string $name = 'Papelería Andina S.A.S.', ?string $number = null): string
    {
        $number ??= (string) random_int(810000000, 819999999);
        $tercero = $this->sendJson('POST', '/api/v1/terceros/quick', [
            'person_type' => 'empresa',
            'identification_type' => 'nit',
            'identification_number' => $number,
            'business_name' => $name,
            'municipality_code' => '11001',
            'email' => 'ventas@papeleria.co',
            'roles' => ['proveedor'],
        ]);
        self::assertResponseStatusCodeSame(201, 'A supplier is created inline: '.json_encode($tercero));

        return $tercero['id'];
    }

    protected function product(string $code = 'RES-01', string $name = 'Resma carta'): string
    {
        $product = $this->sendJson('POST', '/api/v1/products', ['type' => 'producto', 'code' => $code, 'name' => $name, 'sale_price' => '20000', 'price_includes_tax' => false]);
        self::assertResponseStatusCodeSame(201, 'A product: '.json_encode($product));

        return $product['id'];
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed> a line by account (513595) of 2 × 500 000 with IVA 19 %
     */
    protected function orderLine(array $over = []): array
    {
        return $over + [
            'product_id' => null,
            'account_id' => $this->account('513595')->toRfc4122(),
            'description' => 'Mantenimiento de equipos',
            'quantity' => '2',
            'unit_price' => '500000',
            'discount' => '0',
            'charge_tax_id' => $this->taxId('IVA 19 %'),
            'withholdings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    protected function orderPayload(string $supplier, array $over = []): array
    {
        return $over + [
            'tercero_id' => $supplier,
            'issue_date' => self::today(),
            'expected_date' => self::today(10),
            'notes' => 'Entregar en bodega',
            'lines' => [$this->orderLine()],
        ];
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed> the PurchaseOrderOutput
     */
    protected function orderDraft(?string $supplier = null, array $over = []): array
    {
        $order = $this->sendJson('POST', '/api/v1/purchase-orders', $this->orderPayload($supplier ?? $this->supplierWithEmail(), $over));
        self::assertResponseStatusCodeSame(201, 'The order draft is saved: '.json_encode($order));

        return $order;
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed> the emitted PurchaseOrderOutput
     */
    protected function emittedOrder(?string $supplier = null, array $over = []): array
    {
        $draft = $this->orderDraft($supplier, $over);
        $order = $this->sendJson('POST', '/api/v1/purchase-orders/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('Emitted: '.json_encode($order));

        return $order;
    }

    protected function journalRows(): int
    {
        return (int) $this->db()->fetchOne('SELECT COUNT(*) FROM journal_entry WHERE company_id = ?', [$this->company->toBinary()]);
    }
}
