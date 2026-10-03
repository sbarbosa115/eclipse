<?php

namespace App\Tests\Functional\Ledger;

use App\Ledger\Domain\Model\PaymentMethod;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Totals\TaxCalculation;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Symfony\Component\Uid\Uuid;

final class CatalogApiTest extends ApiTestCase
{
    use SignsUp;

    public function testTaxesAndPaymentMethodsAreTheSignedInCompanysOnly(): void
    {
        $other = Uuid::fromString($this->signUp('beto@b.co', '800197268', 'B')['company_id']);
        $this->signOut();
        $mine = Uuid::fromString($this->signUp()['company_id']);
        $this->save(
            new Tax($mine, 'IVA de A', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '19.0000', null, null),
            new Tax($other, 'IVA de B', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '19.0000', null, null),
            new PaymentMethod($mine, 'Crédito de A', PaymentKind::Credit, null),
            new PaymentMethod($other, 'Crédito de B', PaymentKind::Credit, null),
        );

        $taxes = $this->getJson('/api/v1/taxes?class=charge')['items'];
        self::assertResponseIsSuccessful();
        $names = array_column($taxes, 'name');
        self::assertContains('IVA de A', $names, 'Its own taxes show next to the seeded ones.');
        self::assertNotContains('IVA de B', $names, 'Another company\'s taxes never show.');
        self::assertSame('19.0000', $taxes[array_search('IVA de A', $names, true)]['rate'], 'Rates travel as decimal strings.');

        $methods = array_column($this->getJson('/api/v1/payment-methods')['items'], 'name');
        self::assertContains('Crédito de A', $methods);
        self::assertNotContains('Crédito de B', $methods);
    }

    public function testSignedOutIsRefused(): void
    {
        $this->getJson('/api/v1/taxes');

        self::assertResponseStatusCodeSame(401);
    }
}
