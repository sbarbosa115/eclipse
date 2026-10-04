<?php

namespace App\Tests\Functional\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;

/**
 * Security audit 2026-10-04, finding 3: an optional id sent as "" (an empty select) passed `Assert\Uuid` and then
 * reached `Uuid::fromString('')`, a 500. An empty id means "none", like null.
 */
final class EmptyIdFieldsTest extends ApiTestCase
{
    use SignsUp;

    public function testAnEmptyIdOnAProductIsNone(): void
    {
        $this->signUp();
        $product = ['type' => 'producto', 'code' => 'P1', 'name' => 'Silla', 'sale_price' => '1000.00', 'price_includes_tax' => false];

        $created = $this->sendJson('POST', '/api/v1/products', $product + ['category_id' => '', 'charge_tax_id' => '', 'withholding_tax_id' => '', 'revenue_account_id' => '', 'expense_account_id' => '']);
        self::assertResponseStatusCodeSame(201);
        self::assertNull($created['category_id']);

        $this->sendJson('POST', '/api/v1/products/quick', ['type' => 'producto', 'code' => 'P2', 'name' => 'Mesa', 'sale_price' => '1.00', 'price_includes_tax' => false, 'charge_tax_id' => '']);
        self::assertResponseStatusCodeSame(201);

        $this->sendJson('PUT', '/api/v1/products/'.$created['id'], $product + ['category_id' => '', 'expense_account_id' => '']);
        self::assertResponseIsSuccessful();

        $taxes = $this->sendJson('PUT', '/api/v1/products/'.$created['id'].'/taxes', ['charge_tax_id' => '', 'withholding_tax_id' => '']);
        self::assertResponseIsSuccessful();
        self::assertNull($taxes['charge_tax_id']);
    }

    public function testAnEmptyDefaultTaxOnTheCompanyIsNone(): void
    {
        $this->signUp();
        $company = $this->getJson('/api/v1/company');
        unset($company['id'], $company['logo_id']);

        $saved = $this->sendJson('PUT', '/api/v1/company', ['default_charge_tax_id' => '', 'default_withholding_tax_id' => ''] + $company);

        self::assertResponseIsSuccessful();
        self::assertNull($saved['default_charge_tax_id']);
    }

    public function testAnEmptyAccountOrContactIdOnATerceroIsNone(): void
    {
        $this->signUp();
        $tercero = [
            'person_type' => 'empresa', 'identification_type' => 'nit', 'identification_number' => '800197268',
            'business_name' => 'Andina S.A.S.', 'roles' => ['cliente'],
            'receivable_account_id' => '', 'payable_account_id' => '',
            'contacts' => [['id' => '', 'name' => 'Pedro Ruiz']],
        ];

        $created = $this->sendJson('POST', '/api/v1/terceros', $tercero);
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $created['contacts']);

        $this->sendJson('PUT', '/api/v1/terceros/'.$created['id'], $tercero);
        self::assertResponseIsSuccessful();
    }
}
