<?php

namespace App\Tests\Functional\Ledger;

use App\Access\Domain\Model\Role;

final class PaymentMethodApiTest extends CatalogTestCase
{
    /**
     * @return array<string, array<string, mixed>> the company's methods by name, inactive ones too
     */
    private function methodsByName(): array
    {
        return array_column($this->getJson('/api/v1/payment-methods?all=1')['items'], null, 'name');
    }

    public function testANewCompanyStartsWithTheSeededMethods(): void
    {
        $this->signUpOwner();

        $methods = $this->methodsByName();
        self::assertSame(['Crédito', 'Efectivo', 'Tarjeta crédito', 'Tarjeta débito', 'Transferencia'], array_keys($methods));
        self::assertSame('credit', $methods['Crédito']['kind']);
        self::assertNull($methods['Crédito']['account_id'], 'Crédito posts to the tercero\'s receivable or payable account: it has none of its own.');
        foreach (['Efectivo', 'Tarjeta débito', 'Tarjeta crédito', 'Transferencia'] as $name) {
            self::assertSame('cash', $methods[$name]['kind'], "$name is contado.");
            self::assertTrue($methods[$name]['standard']);
        }
    }

    public function testTheOwnerCreatesAMethodOnAPostableAccount(): void
    {
        $company = $this->signUpOwner();
        $bank = $this->account($company, '11100502', 'Bancolombia cuenta corriente');

        $created = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Bancolombia', 'kind' => 'cash', 'account_id' => $bank]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['Bancolombia', 'cash', $bank, '11100502', true, false], [$created['name'], $created['kind'], $created['account_id'], $created['account_code'], $created['active'], $created['standard']]);
        self::assertCount(1, $this->audit($company, 'payment_method.created'));
        $row = $this->audit($company, 'payment_method.created')[0];
        self::assertSame(['payment_method', $created['id']], [$row->subjectType(), $row->subjectId()?->toRfc4122()]);
        self::assertNotNull($row->userId());
        self::assertSame(['name' => 'Bancolombia', 'kind' => 'cash', 'account_id' => $bank], $row->data(), 'The row holds the method as created.');
    }

    public function testAContadoMethodNeedsAPostableAccount(): void
    {
        $company = $this->signUpOwner();
        $group = $this->account($company, '1110', 'Bancos');

        $none = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Sin cuenta', 'kind' => 'cash', 'account_id' => null]);
        self::assertResponseStatusCodeSame(422, 'Contado money has to land somewhere.');
        self::assertSame('account_id', $none['violations'][0]['field']);

        $grouping = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Grupo', 'kind' => 'cash', 'account_id' => $group]);
        self::assertResponseStatusCodeSame(422, 'Entries post to subcuentas and auxiliares, not to an account that groups others.');
        self::assertSame('account_id', $grouping['violations'][0]['field']);

        $unknown = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Fantasma', 'kind' => 'cash', 'account_id' => '0197a7c0-0000-7000-8000-000000000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('account_id', $unknown['violations'][0]['field']);
    }

    public function testAnotherCompanysAccountCannotBeChosen(): void
    {
        $other = $this->signUpOwner('b@b.co', '800197268', 'B');
        $foreign = $this->account($other, '11100501');
        $this->signOut();
        $this->signUpOwner();

        $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Robada', 'kind' => 'cash', 'account_id' => $foreign]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testACreditMethodHasNoAccount(): void
    {
        $company = $this->signUpOwner();
        $bank = $this->account($company, '11100502');

        $with = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Crédito 90', 'kind' => 'credit', 'account_id' => $bank]);
        self::assertResponseStatusCodeSame(422, 'A crédito method posts to the tercero\'s account, never its own.');
        self::assertSame('account_id', $with['violations'][0]['field']);

        $without = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Crédito 90', 'kind' => 'credit', 'account_id' => null]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('credit', $without['kind']);
    }

    public function testNamesAreUniquePerCompany(): void
    {
        $company = $this->signUpOwner();
        $bank = $this->account($company, '11100502');

        $body = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'efectivo', 'kind' => 'cash', 'account_id' => $bank]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('name', $body['violations'][0]['field']);
    }

    public function testEditingChangesTheNameAndTheAccountButNotTheKind(): void
    {
        $company = $this->signUpOwner();
        $bank = $this->account($company, '11100502');
        $id = $this->methodsByName()['Transferencia']['id'];

        $updated = $this->sendJson('PUT', "/api/v1/payment-methods/$id", ['name' => 'Transferencia Bancolombia', 'account_id' => $bank]);

        self::assertResponseIsSuccessful();
        self::assertSame(['Transferencia Bancolombia', 'cash', $bank], [$updated['name'], $updated['kind'], $updated['account_id']]);
        self::assertCount(1, $this->audit($company, 'payment_method.updated'));

        $credit = $this->methodsByName()['Crédito']['id'];
        $this->sendJson('PUT', "/api/v1/payment-methods/$credit", ['name' => 'Crédito', 'account_id' => $bank]);
        self::assertResponseStatusCodeSame(422, 'Giving a crédito method an account would change what it is.');
    }

    public function testBillingUsersReadButCannotChangeMethods(): void
    {
        $company = $this->signUpOwner();
        $id = $this->methodsByName()['Efectivo']['id'];
        $this->signInAs(Role::Billing, $company);

        $this->getJson('/api/v1/payment-methods');
        self::assertResponseIsSuccessful();
        $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'X', 'kind' => 'credit', 'account_id' => null]);
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('PUT', "/api/v1/payment-methods/$id", ['name' => 'X', 'account_id' => null]);
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('POST', "/api/v1/payment-methods/$id/deactivate", []);
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('DELETE', "/api/v1/payment-methods/$id", []);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAccountantMayEditToo(): void
    {
        $company = $this->signUpOwner();
        $bank = $this->account($company, '11100502');
        $this->signInAs(Role::Accountant, $company);

        $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Davivienda', 'kind' => 'cash', 'account_id' => $bank]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testAMethodOfAnotherCompanyIsNotFound(): void
    {
        $this->signUpOwner('b@b.co', '800197268', 'B');
        $foreign = $this->methodsByName()['Efectivo']['id'];
        $this->signOut();
        $this->signUpOwner();

        foreach ([['PUT', "/api/v1/payment-methods/$foreign", ['name' => 'X', 'account_id' => null]], ['POST', "/api/v1/payment-methods/$foreign/deactivate", []], ['POST', "/api/v1/payment-methods/$foreign/activate", []], ['DELETE', "/api/v1/payment-methods/$foreign", []]] as [$method, $uri, $payload]) {
            $this->sendJson($method, $uri, $payload);
            self::assertResponseStatusCodeSame(404, "$method $uri answers 404 for another company's method.");
        }
    }

    public function testDeactivatingHidesTheMethodFromPickers(): void
    {
        $company = $this->signUpOwner();
        $id = $this->methodsByName()['Tarjeta débito']['id'];

        $off = $this->sendJson('POST', "/api/v1/payment-methods/$id/deactivate", []);

        self::assertFalse($off['active']);
        self::assertNotContains('Tarjeta débito', array_column($this->getJson('/api/v1/payment-methods')['items'], 'name'));
        $on = $this->sendJson('POST', "/api/v1/payment-methods/$id/activate", []);
        self::assertTrue($on['active']);
        self::assertCount(1, $this->audit($company, 'payment_method.deactivated'));
        self::assertCount(1, $this->audit($company, 'payment_method.activated'));
    }

    public function testAnUnusedMethodIsDeleted(): void
    {
        $company = $this->signUpOwner();
        $created = $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'Nequi', 'kind' => 'credit', 'account_id' => null]);

        $this->sendJson('DELETE', '/api/v1/payment-methods/'.$created['id'], []);

        self::assertResponseStatusCodeSame(204);
        self::assertArrayNotHasKey('Nequi', $this->methodsByName());
        self::assertCount(1, $this->audit($company, 'payment_method.deleted'));
    }

    public function testAMethodUsedByADocumentCannotBeDeletedOnlyDeactivated(): void
    {
        $company = $this->signUpOwner();
        $id = $this->methodsByName()['Efectivo']['id'];
        $this->usePaymentMethodOnAnInvoice($company, $id);

        $body = $this->sendJson('DELETE', "/api/v1/payment-methods/$id", []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('payment_method_in_use', $body['error']);
        $this->sendJson('POST', "/api/v1/payment-methods/$id/deactivate", []);
        self::assertResponseIsSuccessful('Deactivating is what a used method allows.');
    }

    public function testSignedOutIsRefused(): void
    {
        $this->sendJson('POST', '/api/v1/payment-methods', ['name' => 'X', 'kind' => 'credit', 'account_id' => null]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheSettingsListShowsWhatIsInUseAndInactiveMethods(): void
    {
        $company = $this->signUpOwner();
        $efectivo = $this->methodsByName()['Efectivo']['id'];
        $this->usePaymentMethodOnAnInvoice($company, $efectivo);
        $this->sendJson('POST', '/api/v1/payment-methods/'.$this->methodsByName()['Transferencia']['id'].'/deactivate', []);

        $items = array_column($this->getJson('/api/v1/settings/payment-methods')['items'], null, 'name');

        self::assertResponseIsSuccessful();
        self::assertTrue($items['Efectivo']['in_use']);
        self::assertFalse($items['Crédito']['in_use']);
        self::assertFalse($items['Transferencia']['active'], 'Inactive methods are listed here so they can be reactivated.');
    }
}
