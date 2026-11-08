<?php

namespace App\Tests\Functional\Observations;

use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Item 0 of the observations-2026-11 PRD: the contract the items build on. Lines keep their retenciones in a list
 * (the single one of stage 1 is its first) and DIVIPOLA answers. (Its list of routes answering 501 went as each item built
 * them.)
 */
final class ContractTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testALinesRetencionIsTheFirstOfItsListWithItsBaseAndShare(): void
    {
        $client = $this->client();
        $product = $this->service();

        $invoice = $this->draft($this->payload($client, $product, [
            'lines' => [$this->line($product, ['quantity' => '2', 'discount' => '10', 'withholding_tax_id' => $this->taxId('ReteFuente servicios 4 %')])],
        ]));

        $line = $invoice['lines'][0];
        self::assertCount(1, $line['withholdings'], 'withholding_tax_id still means one retención (deprecated alias, PRD §6).');
        self::assertSame(['ReteFuente servicios 4 %', 'retefuente', '4.0000', '1800000.00', '72000.00'], [
            $line['withholdings'][0]['name'], $line['withholdings'][0]['kind'], $line['withholdings'][0]['rate'], $line['withholdings'][0]['base_amount'], $line['withholdings'][0]['amount'],
        ], 'Each retención keeps its own snapshot, its base (the discounted subtotal) and its share.');
        self::assertSame('ReteFuente servicios 4 %', $line['withholding_tax_name'], 'The deprecated single field reads the first retención.');
        self::assertArrayHasKey('product_code', $line, 'Lines carry the product code (written by the "product-code" item).');
        self::assertSame([['tax_id' => $this->taxId('ReteFuente servicios 4 %'), 'name' => 'ReteFuente servicios 4 %', 'kind' => 'retefuente', 'rate' => '4.0000', 'base' => '1800000.00', 'amount' => '72000.00']], $invoice['withholdings_by_tax'], 'The document lists its retenciones by tax for the totals panel.');
    }

    public function testALineWithoutRetencionHasAnEmptyList(): void
    {
        $client = $this->client();
        $product = $this->service();

        $invoice = $this->draft($this->payload($client, $product));

        self::assertSame([], $invoice['lines'][0]['withholdings']);
        self::assertSame([], $invoice['withholdings_by_tax']);
        self::assertSame('0.00', $invoice['withholding_total']);
    }

    public function testDivipolaListsDepartmentsAndFindsMunicipalitiesByNameOrCode(): void
    {
        $departments = $this->getJson('/api/v1/geo/departments')['items'];
        self::assertCount(33, $departments, '32 departamentos and Bogotá D.C.');
        self::assertSame(['code' => '05', 'name' => 'ANTIOQUIA'], $departments[0]);

        $bogota = $this->getJson('/api/v1/geo/municipalities?q=bogota')['items'];
        self::assertContains('11001', array_column($bogota, 'code'), 'A search ignores accents and case.');
        self::assertSame(['05001'], array_column($this->getJson('/api/v1/geo/municipalities?q=05001')['items'], 'code'), 'A code prefix finds it.');
        $antioquia = $this->getJson('/api/v1/geo/municipalities?department=05&q=medel')['items'];
        self::assertSame([['code' => '05001', 'name' => 'MEDELLÍN', 'department_code' => '05', 'department_name' => 'ANTIOQUIA']], $antioquia);
        self::assertSame([], $this->getJson('/api/v1/geo/municipalities?q=%25')['items'], '% matches literally.');
    }

    public function testDivipolaNeedsASignedInUser(): void
    {
        $this->client->request('POST', '/api/v1/auth/sign-out');
        $this->client->request('GET', '/api/v1/geo/municipalities?q=bogota');

        self::assertResponseStatusCodeSame(401);
    }
}
