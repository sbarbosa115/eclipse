<?php

namespace App\Tests\Functional\Catalog;

use App\Access\Application\Query\Users;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\UI\Http\Security\SecurityUser;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use App\Shared\Domain\Totals\TaxCalculation;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * §4.3: products and services, their categories, and what a document line reads from them.
 */
final class ProductApiTest extends ApiTestCase
{
    use SignsUp;

    private Uuid $company;
    private string $iva19;
    private string $iva5;
    private string $impoconsumo;
    private string $reteFuente;
    private string $inactiveIva;
    private string $postable;
    private string $groupAccount;

    private function startCompany(string $email = 'ana@acme.co', string $nit = '900123456', string $name = 'Acme S.A.S.'): void
    {
        $this->company = Uuid::fromString($this->signUp($email, $nit, $name)['company_id']);
        $iva19 = new Tax($this->company, 'IVA 19 %', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '19.0000', null, null);
        $iva5 = new Tax($this->company, 'IVA 5 %', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '5.0000', null, null);
        $consumo = new Tax($this->company, 'Impoconsumo 500', TaxClass::Charge, TaxKind::Consumption, TaxCalculation::PerUnit, '500.0000', null, null);
        $rete = new Tax($this->company, 'ReteFuente 4 %', TaxClass::Withholding, TaxKind::IncomeWithholding, TaxCalculation::Percentage, '4.0000', null, null);
        $old = new Tax($this->company, 'IVA viejo', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '16.0000', null, null);
        $this->setInactive($old);
        $postable = new Account($this->company, '413595', 'Venta de otros', AccountNature::Credit, '4135', false);
        $group = new Account($this->company, '4135', 'Comercio', AccountNature::Credit, '41', false);
        $this->save($iva19, $iva5, $consumo, $rete, $old, $postable, $group);
        $this->iva19 = $iva19->id()->toRfc4122();
        $this->iva5 = $iva5->id()->toRfc4122();
        $this->impoconsumo = $consumo->id()->toRfc4122();
        $this->reteFuente = $rete->id()->toRfc4122();
        $this->inactiveIva = $old->id()->toRfc4122();
        $this->postable = $postable->id()->toRfc4122();
        $this->groupAccount = $group->id()->toRfc4122();
    }

    private function setInactive(Tax $tax): void
    {
        (new \ReflectionProperty($tax, 'active'))->setValue($tax, false);
    }

    /** The company's default taxes (Configuración) are the company item's to edit; here they are set in the table. */
    private function setCompanyDefaults(?string $charge, ?string $withholding): void
    {
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('UPDATE company SET default_charge_tax_id = ?, default_withholding_tax_id = ? WHERE id = ?', [
            null === $charge ? null : Uuid::fromString($charge)->toBinary(),
            null === $withholding ? null : Uuid::fromString($withholding)->toBinary(),
            $this->company->toBinary(),
        ]);
    }

    /** Signs a user of another role in, in the signed-in company. */
    private function signInAs(Role $role): void
    {
        $email = $role->value.'@acme.co';
        $user = User::invite($this->company, $email, ucfirst($role->value), $role, new \DateTimeImmutable());
        (new \ReflectionProperty($user, 'passwordHash'))->setValue($user, 'not-a-real-hash');
        (new \ReflectionProperty($user, 'status'))->setValue($user, \App\Access\Domain\Model\UserStatus::Active);
        $this->save($user);
        $view = static::getContainer()->get(Users::class)->byEmail($email);
        \assert(null !== $view);
        $this->client->loginUser(SecurityUser::from($view), 'api');
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    private function createProduct(string $code = 'P-001', string $name = 'Cuaderno', array $extra = []): array
    {
        return $this->sendJson('POST', '/api/v1/products', $extra + ['type' => 'producto', 'code' => $code, 'name' => $name, 'sale_price' => '10000', 'price_includes_tax' => false]);
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<string>
     */
    private function violationFields(array $body): array
    {
        return array_column($body['violations'] ?? [], 'field');
    }

    public function testTheOwnerCreatesAProductWithItsDefaults(): void
    {
        $this->startCompany();
        $this->setCompanyDefaults($this->iva19, $this->reteFuente);

        $product = $this->createProduct();

        self::assertResponseStatusCodeSame(201);
        self::assertSame('producto', $product['type']);
        self::assertSame('94', $product['unit_code'], 'A product with no unit is "94 unidad".');
        self::assertSame('10000.0000', $product['sale_price'], 'Prices travel as four-decimal strings.');
        self::assertSame('10000.0000', $product['unit_price_net_of_tax'], 'The price does not include the tax: the line value is the price.');
        self::assertSame($this->iva19, $product['charge_tax_id'], 'A product naming no tax takes the company\'s default charge tax.');
        self::assertSame($this->reteFuente, $product['withholding_tax_id'], '… and its default withholding.');
        self::assertTrue($product['active']);
        self::assertNull($product['category_id']);

        $service = $this->createProduct('S-001', 'Asesoría', ['type' => 'servicio']);
        self::assertSame('ZZ', $service['unit_code'], 'A service with no unit is a "servicio".');

        $this->createProduct('K-001', 'Arroz', ['unit_code' => 'KGM', 'charge_tax_id' => $this->iva5, 'description' => '  Arroz blanco  ']);
        $shown = $this->getJson('/api/v1/products/'.$this->idOf('K-001'));
        self::assertSame('KGM', $shown['unit_code']);
        self::assertSame($this->iva5, $shown['charge_tax_id'], 'A tax the product names wins over the default.');
        self::assertSame('Arroz blanco', $shown['description'], 'Text is trimmed.');
    }

    private function idOf(string $code): string
    {
        foreach ($this->getJson('/api/v1/products?q='.$code)['items'] as $item) {
            if ($item['code'] === $code) {
                return $item['id'];
            }
        }
        self::fail("No product $code.");
    }

    public function testAPriceThatIncludesIvaCarriesTheNetValueForTheLine(): void
    {
        $this->startCompany();

        $exact = $this->createProduct('A', 'Exacto', ['sale_price' => '119000', 'price_includes_tax' => true, 'charge_tax_id' => $this->iva19]);
        self::assertSame('119000.0000', $exact['sale_price'], 'The list price is kept as typed.');
        self::assertSame('100000.0000', $exact['unit_price_net_of_tax'], '119 000 with IVA 19 % is 100 000 net.');

        $odd = $this->createProduct('B', 'Redondeo', ['sale_price' => '50000', 'price_includes_tax' => true, 'charge_tax_id' => $this->iva19]);
        self::assertSame('42016.8067', $odd['unit_price_net_of_tax'], 'Four decimals, so one unit totals 50 000.00 at the document.');

        $consumo = $this->createProduct('C', 'Impoconsumo', ['sale_price' => '10000', 'price_includes_tax' => true, 'charge_tax_id' => $this->impoconsumo]);
        self::assertSame('9500.0000', $consumo['unit_price_net_of_tax'], 'A tax per unit is subtracted as a value.');

        $listed = $this->getJson('/api/v1/products?q=Redondeo')['items'][0];
        self::assertSame('42016.8067', $listed['unit_price_net_of_tax'], 'The list carries it too: documents pick from it.');

        $tooSmall = $this->createProduct('D', 'Barato', ['sale_price' => '400', 'price_includes_tax' => true, 'charge_tax_id' => $this->impoconsumo]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['sale_price'], $this->violationFields($tooSmall), 'A price under the tax it includes is refused on the price.');
    }

    public function testTheCodeIsUniquePerCompany(): void
    {
        $this->startCompany('beto@b.co', '800197268', 'B');
        $this->createProduct('P-001', 'De B');
        $this->signOut();

        $this->startCompany();
        $this->createProduct('P-001', 'Cuaderno');
        self::assertResponseStatusCodeSame(201, 'Another company\'s código does not count.');

        $duplicate = $this->createProduct('P-001', 'Otro');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $duplicate['error']);
        self::assertSame(['code'], $this->violationFields($duplicate), 'duplicate_code is reported on the code field.');

        $quick = $this->sendJson('POST', '/api/v1/products/quick', ['type' => 'producto', 'code' => 'P-001', 'name' => 'Otro', 'sale_price' => '1']);
        self::assertResponseStatusCodeSame(422, 'Quick-create follows the same rule.');
        self::assertSame(['code'], $this->violationFields($quick));
    }

    public function testRequiredFieldsAndShapesAreChecked(): void
    {
        $this->startCompany();

        $body = $this->sendJson('POST', '/api/v1/products', ['type' => 'cosa', 'code' => '', 'name' => '', 'sale_price' => '12,5', 'unit_code' => 'XYZ']);

        self::assertResponseStatusCodeSame(422);
        $fields = $this->violationFields($body);
        foreach (['type', 'code', 'name', 'sale_price', 'unit_code'] as $field) {
            self::assertContains($field, $fields, "$field is validated.");
        }

        $this->sendJson('POST', '/api/v1/products', ['type' => 'producto', 'code' => 'X', 'name' => 'X', 'sale_price' => '1.23456']);
        self::assertResponseStatusCodeSame(422, 'Five decimals are refused: unit prices keep four.');
    }

    public function testTaxesMustBeActiveAndOfTheRightClass(): void
    {
        $this->startCompany('beto@b.co', '800197268', 'B');
        $other = $this->iva19;
        $this->signOut();
        $this->startCompany();

        $body = $this->createProduct('T', 'T', [
            'charge_tax_id' => $this->reteFuente, 'withholding_tax_id' => $this->iva19,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['charge_tax_id', 'withholding_tax_id'], $this->violationFields($body), 'A withholding is not a charge tax and the other way round.');

        $this->createProduct('T', 'T', ['charge_tax_id' => $this->inactiveIva]);
        self::assertResponseStatusCodeSame(422, 'An inactive tax is refused.');

        $body = $this->createProduct('T', 'T', ['charge_tax_id' => $other]);
        self::assertResponseStatusCodeSame(422, 'Another company\'s tax does not exist here.');
        self::assertSame(['charge_tax_id'], $this->violationFields($body));

        $this->createProduct('T', 'T', ['charge_tax_id' => Uuid::v7()->toRfc4122()]);
        self::assertResponseStatusCodeSame(422, 'An unknown tax id is a violation, not a 404.');
    }

    public function testAccountsMustBePostable(): void
    {
        $this->startCompany();

        $ok = $this->createProduct('R', 'R', ['revenue_account_id' => $this->postable, 'expense_account_id' => $this->postable]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->postable, $ok['revenue_account_id']);
        self::assertSame($this->postable, $ok['expense_account_id']);

        $body = $this->createProduct('R2', 'R2', ['revenue_account_id' => $this->groupAccount, 'expense_account_id' => Uuid::v7()->toRfc4122()]);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['revenue_account_id', 'expense_account_id'], $this->violationFields($body), 'A group account does not take entries and an unknown one does not exist.');
    }

    public function testQuickCreateTakesWhatALineNeeds(): void
    {
        $this->startCompany();
        $this->setCompanyDefaults($this->iva19, null);

        $product = $this->sendJson('POST', '/api/v1/products/quick', ['type' => 'servicio', 'code' => 'Q1', 'name' => 'Hora de consultoría', 'sale_price' => '119000', 'price_includes_tax' => true]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->iva19, $product['charge_tax_id'], 'The company\'s default IVA fills in.');
        self::assertNull($product['withholding_tax_id']);
        self::assertSame('100000.0000', $product['unit_price_net_of_tax'], 'With the default IVA included, the line value is net.');
        self::assertSame('ZZ', $product['unit_code']);
        self::assertArrayHasKey('category_name', $product, 'Fields of the contract are all there.');

        $this->sendJson('POST', '/api/v1/products/quick', ['type' => 'servicio', 'code' => 'Q2', 'name' => '', 'sale_price' => 'abc']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheListSearchesFiltersAndPaginates(): void
    {
        $this->startCompany();
        $this->createProduct('A-1', 'Arroz');
        $this->createProduct('A-2', 'Azúcar');
        $this->createProduct('S-1', 'Consultoría', ['type' => 'servicio']);
        $this->createProduct('P%1', '100% algodón');
        $inactive = $this->createProduct('Z-9', 'Zapato viejo');
        $this->sendJson('POST', '/api/v1/products/'.$inactive['id'].'/deactivate', []);

        $all = $this->getJson('/api/v1/products');
        self::assertSame(5, $all['total']);
        self::assertSame(1, $all['page']);
        self::assertSame(20, $all['per_page']);
        self::assertSame(['100% algodón', 'Arroz', 'Azúcar', 'Consultoría', 'Zapato viejo'], array_column($all['items'], 'name'), 'By name.');

        self::assertSame(['A-1'], array_column($this->getJson('/api/v1/products?q=arr')['items'], 'code'), 'q matches the name.');
        self::assertSame(['S-1'], array_column($this->getJson('/api/v1/products?q=S-1')['items'], 'code'), 'q matches the code.');
        self::assertSame(['P%1'], array_column($this->getJson('/api/v1/products?q=%25')['items'], 'code'), 'A % in q is literal.');
        self::assertSame([], $this->getJson('/api/v1/products?q=nada')['items']);
        self::assertSame(['S-1'], array_column($this->getJson('/api/v1/products?type=servicio')['items'], 'code'));
        self::assertSame(['Z-9'], array_column($this->getJson('/api/v1/products?active=0')['items'], 'code'), 'active=0 are the inactive ones.');
        self::assertSame(4, $this->getJson('/api/v1/products?active=1')['total'], 'active=1 are the active ones.');

        $page = $this->getJson('/api/v1/products?per_page=2&page=2');
        self::assertSame(['Azúcar', 'Consultoría'], array_column($page['items'], 'name'));
        self::assertSame(5, $page['total'], 'The total counts every match, not the page.');
        self::assertSame(2, $page['per_page']);
        self::assertCount(5, $this->getJson('/api/v1/products?per_page=1000')['items'], 'per_page is capped at 100, not refused.');
    }

    public function testAnotherCompanysProductsAreNeverSeen(): void
    {
        $this->startCompany('beto@b.co', '800197268', 'B');
        $theirs = $this->createProduct('B-1', 'De B');
        $this->signOut();
        $this->startCompany();
        $this->createProduct('A-1', 'De A');

        self::assertSame(['A-1'], array_column($this->getJson('/api/v1/products')['items'], 'code'));
        $id = $theirs['id'];
        $this->getJson("/api/v1/products/$id");
        self::assertResponseStatusCodeSame(404, 'Another company\'s id answers 404, not 403.');
        $this->sendJson('PUT', "/api/v1/products/$id", ['type' => 'producto', 'code' => 'X', 'name' => 'X', 'sale_price' => '1']);
        self::assertResponseStatusCodeSame(404);
        $this->sendJson('PUT', "/api/v1/products/$id/taxes", ['charge_tax_id' => null, 'withholding_tax_id' => null]);
        self::assertResponseStatusCodeSame(404);
        $this->sendJson('POST', "/api/v1/products/$id/deactivate", []);
        self::assertResponseStatusCodeSame(404);
        $this->sendJson('POST', "/api/v1/products/$id/reactivate", []);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', "/api/v1/products/$id");
        self::assertResponseStatusCodeSame(404);
        $this->getJson('/api/v1/products/not-an-id');
        self::assertResponseStatusCodeSame(404, 'A malformed id is just not found.');
    }

    public function testUpdatingRewritesTheProduct(): void
    {
        $this->startCompany();
        $this->setCompanyDefaults($this->iva19, $this->reteFuente);
        $a = $this->createProduct('A', 'Uno');
        $this->createProduct('B', 'Dos');
        $category = $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'Papelería']);

        $updated = $this->sendJson('PUT', '/api/v1/products/'.$a['id'], [
            'type' => 'servicio', 'code' => 'A', 'name' => 'Uno revisado', 'description' => 'Nueva', 'category_id' => $category['id'],
            'unit_code' => 'HUR', 'sale_price' => '238000', 'price_includes_tax' => true, 'charge_tax_id' => $this->iva19,
            'revenue_account_id' => $this->postable,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('servicio', $updated['type']);
        self::assertSame('Uno revisado', $updated['name']);
        self::assertSame('Papelería', $updated['category_name']);
        self::assertSame('HUR', $updated['unit_code']);
        self::assertSame('200000.0000', $updated['unit_price_net_of_tax']);
        self::assertNull($updated['withholding_tax_id'], 'An update writes what it is given: no withholding is none, not the default.');
        self::assertSame($this->postable, $updated['revenue_account_id']);

        $this->sendJson('PUT', '/api/v1/products/'.$a['id'], ['type' => 'producto', 'code' => 'B', 'name' => 'Uno', 'sale_price' => '1']);
        self::assertResponseStatusCodeSame(422);
        $this->sendJson('PUT', '/api/v1/products/'.$a['id'], ['type' => 'producto', 'code' => 'A', 'name' => 'Uno', 'sale_price' => '1']);
        self::assertResponseIsSuccessful('Saving a product with its own código is not a duplicate.');
    }

    public function testUseTheseTaxesFromNowOn(): void
    {
        $this->startCompany();
        $product = $this->createProduct('A', 'Uno', ['sale_price' => '105000', 'price_includes_tax' => true, 'charge_tax_id' => $this->iva19]);

        $changed = $this->sendJson('PUT', '/api/v1/products/'.$product['id'].'/taxes', ['charge_tax_id' => $this->iva5, 'withholding_tax_id' => $this->reteFuente]);

        self::assertResponseIsSuccessful();
        self::assertSame($this->iva5, $changed['charge_tax_id']);
        self::assertSame($this->reteFuente, $changed['withholding_tax_id']);
        self::assertSame('100000.0000', $changed['unit_price_net_of_tax'], 'The line value follows the new tax.');

        $none = $this->sendJson('PUT', '/api/v1/products/'.$product['id'].'/taxes', ['charge_tax_id' => null, 'withholding_tax_id' => null]);
        self::assertNull($none['charge_tax_id']);
        self::assertSame('105000.0000', $none['unit_price_net_of_tax'], 'Without a charge tax there is nothing to take out.');

        $body = $this->sendJson('PUT', '/api/v1/products/'.$product['id'].'/taxes', ['charge_tax_id' => $this->reteFuente, 'withholding_tax_id' => null]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['charge_tax_id'], $this->violationFields($body));
    }

    public function testDeactivatingKeepsTheProductAndReactivatingBringsItBack(): void
    {
        $this->startCompany();
        $product = $this->createProduct();

        $off = $this->sendJson('POST', '/api/v1/products/'.$product['id'].'/deactivate', []);
        self::assertResponseIsSuccessful();
        self::assertFalse($off['active']);
        self::assertSame(0, $this->getJson('/api/v1/products?active=1')['total'], 'Document pickers ask for the active ones only.');
        self::assertSame(1, $this->getJson('/api/v1/products')['total'], 'The full list still has it.');

        $on = $this->sendJson('POST', '/api/v1/products/'.$product['id'].'/reactivate', []);
        self::assertTrue($on['active']);
    }

    public function testAnUnusedProductIsDeletedAndAUsedOneOnlyDeactivated(): void
    {
        $this->startCompany();
        $free = $this->createProduct('F', 'Libre');
        $used = $this->createProduct('U', 'Usado');
        $this->insertQuotationLineUsing($used['id']);

        $this->client->request('DELETE', '/api/v1/products/'.$free['id']);
        self::assertResponseStatusCodeSame(204);
        $this->getJson('/api/v1/products/'.$free['id']);
        self::assertResponseStatusCodeSame(404, 'It is gone.');

        $body = $this->deleteJson('/api/v1/products/'.$used['id']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('product_in_use', $body['error'], 'A product a document used cannot be deleted.');
        $this->sendJson('POST', '/api/v1/products/'.$used['id'].'/deactivate', []);
        self::assertResponseIsSuccessful('It can be deactivated instead.');
    }

    /**
     * @return array<mixed>
     */
    private function deleteJson(string $uri): array
    {
        $this->client->request('DELETE', $uri, server: ['HTTP_ACCEPT' => 'application/json']);

        return $this->body();
    }

    private function insertQuotationLineUsing(string $productId): void
    {
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $db->insert('quotation_line', [
            'id' => Uuid::v7()->toBinary(), 'gross_amount' => 0, 'discount_amount' => 0, 'subtotal_amount' => 0, 'tax_amount' => 0, 'withholding_amount' => 0, 'total_amount' => 0,
            'company_id' => $this->company->toBinary(), 'position' => 1, 'product_id' => Uuid::fromString($productId)->toBinary(), 'description' => 'Línea',
            'quantity' => 1, 'unit_price' => 1, 'discount' => 0,
            'charge_name' => 'Sin IVA', 'charge_kind' => 'none', 'charge_calculation' => 'percentage', 'charge_value' => 0,
            'withholding_name' => 'Sin retención', 'withholding_kind' => 'none', 'withholding_calculation' => 'percentage', 'withholding_value' => 0,
            'document_id' => Uuid::v7()->toBinary(),
        ]);
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCategoriesAreAFlatListPerCompany(): void
    {
        $this->startCompany('beto@b.co', '800197268', 'B');
        $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'De B']);
        $this->signOut();
        $this->startCompany();

        $papeleria = $this->sendJson('POST', '/api/v1/product-categories', ['name' => '  Papelería ']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Papelería', $papeleria['name']);
        self::assertSame(0, $papeleria['product_count']);
        $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'Aseo']);

        $dup = $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'Aseo']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['name'], $this->violationFields($dup));
        $this->sendJson('POST', '/api/v1/product-categories', ['name' => '']);
        self::assertResponseStatusCodeSame(422);

        $this->createProduct('A', 'Uno', ['category_id' => $papeleria['id']]);
        $list = $this->getJson('/api/v1/product-categories')['items'];
        self::assertSame(['Aseo', 'Papelería'], array_column($list, 'name'), 'By name, and only this company\'s.');
        self::assertSame([0, 1], array_column($list, 'product_count'));

        $renamed = $this->sendJson('PUT', '/api/v1/product-categories/'.$papeleria['id'], ['name' => 'Útiles']);
        self::assertResponseIsSuccessful();
        self::assertSame('Útiles', $renamed['name']);
        self::assertSame('Útiles', $this->getJson('/api/v1/products')['items'][0]['category_name'], 'A rename shows in the products.');
        $this->sendJson('PUT', '/api/v1/product-categories/'.$papeleria['id'], ['name' => 'Aseo']);
        self::assertResponseStatusCodeSame(422, 'Renaming onto another category\'s name is refused.');
        $this->sendJson('PUT', '/api/v1/product-categories/'.$papeleria['id'], ['name' => 'Útiles']);
        self::assertResponseIsSuccessful('Keeping its own name is fine.');
    }

    public function testAProductCannotUseAnotherCompanysCategory(): void
    {
        $this->startCompany('beto@b.co', '800197268', 'B');
        $theirs = $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'De B']);
        $this->signOut();
        $this->startCompany();

        $body = $this->createProduct('A', 'Uno', ['category_id' => $theirs['id']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['category_id'], $this->violationFields($body));
        $this->sendJson('PUT', '/api/v1/product-categories/'.$theirs['id'], ['name' => 'Mío']);
        self::assertResponseStatusCodeSame(404, 'Another company\'s category cannot be renamed.');
    }

    public function testTheUnitsAreTheShortDianList(): void
    {
        $this->startCompany();

        $units = $this->getJson('/api/v1/products/units')['items'];

        self::assertSame(['94', 'KGM', 'MTR', 'HUR', 'ZZ'], array_column($units, 'code'));
        self::assertSame(['Unidad', 'Kilogramo', 'Metro', 'Hora', 'Servicio'], array_column($units, 'name'));
    }

    public function testBillingWritesAndTheAccountantOnlyReads(): void
    {
        $this->startCompany();
        $product = $this->createProduct('A', 'Uno');
        $category = $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'Aseo']);

        $this->signInAs(Role::Accountant);
        self::assertSame(1, $this->getJson('/api/v1/products')['total'], 'The accountant reads products.');
        $this->getJson('/api/v1/products/'.$product['id']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->getJson('/api/v1/product-categories')['items']);
        self::assertCount(5, $this->getJson('/api/v1/products/units')['items']);

        $payload = ['type' => 'producto', 'code' => 'N', 'name' => 'N', 'sale_price' => '1'];
        foreach ([
            ['POST', '/api/v1/products', $payload],
            ['POST', '/api/v1/products/quick', $payload],
            ['PUT', '/api/v1/products/'.$product['id'], $payload],
            ['PUT', '/api/v1/products/'.$product['id'].'/taxes', ['charge_tax_id' => null, 'withholding_tax_id' => null]],
            ['POST', '/api/v1/products/'.$product['id'].'/deactivate', []],
            ['POST', '/api/v1/products/'.$product['id'].'/reactivate', []],
            ['POST', '/api/v1/product-categories', ['name' => 'Nueva']],
            ['PUT', '/api/v1/product-categories/'.$category['id'], ['name' => 'Otra']],
        ] as [$method, $uri, $body]) {
            $this->sendJson($method, $uri, $body);
            self::assertResponseStatusCodeSame(403, "The accountant cannot $method $uri.");
        }
        $this->client->request('DELETE', '/api/v1/products/'.$product['id']);
        self::assertResponseStatusCodeSame(403);

        $this->signInAs(Role::Billing);
        $this->createProduct('N', 'Nuevo');
        self::assertResponseStatusCodeSame(201, 'A billing user writes the catalog.');
        $this->sendJson('POST', '/api/v1/product-categories', ['name' => 'Nueva']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testSignedOutIsRefused(): void
    {
        $this->getJson('/api/v1/products');
        self::assertResponseStatusCodeSame(401);
        $this->sendJson('POST', '/api/v1/products/quick', []);
        self::assertResponseStatusCodeSame(401);
        $this->getJson('/api/v1/product-categories');
        self::assertResponseStatusCodeSame(401);
    }
}
