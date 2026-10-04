<?php

namespace App\Tests\Functional\Ledger;

use App\Access\Domain\Model\Role;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use Symfony\Component\Uid\Uuid;

final class TaxApiTest extends CatalogTestCase
{
    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function ivaPayload(array $override = []): array
    {
        return $override + ['name' => 'IVA 16 %', 'tax_class' => 'charge', 'kind' => 'iva', 'calculation' => 'percentage', 'rate' => '16.0000'];
    }

    /**
     * @return array<string, array<string, mixed>> the company's taxes by name
     */
    private function taxesByName(): array
    {
        $items = $this->getJson('/api/v1/taxes?all=1')['items'];

        return array_column($items, null, 'name');
    }

    public function testANewCompanyStartsWithTheSeededTaxes(): void
    {
        $this->signUpOwner();

        $taxes = $this->taxesByName();
        $expected = [
            'IVA 19 %' => ['charge', 'iva', '19.0000'],
            'IVA 5 %' => ['charge', 'iva', '5.0000'],
            'IVA 0 %' => ['charge', 'iva', '0.0000'],
            'IVA por servicios 19 %' => ['charge', 'iva', '19.0000'],
            'Impoconsumo 8 %' => ['charge', 'impoconsumo', '8.0000'],
            'Impoconsumo por valor' => ['charge', 'impoconsumo', '0.0000'],
            'ReteFuente servicios 4 %' => ['withholding', 'retefuente', '4.0000'],
            'ReteFuente compras 2,5 %' => ['withholding', 'retefuente', '2.5000'],
            'ReteFuente honorarios 10 %' => ['withholding', 'retefuente', '10.0000'],
            'ReteFuente honorarios 11 %' => ['withholding', 'retefuente', '11.0000'],
            'ReteIVA 15 %' => ['withholding', 'reteiva', '15.0000'],
        ];
        foreach ($expected as $name => [$class, $kind, $rate]) {
            self::assertArrayHasKey($name, $taxes, "$name is seeded.");
            self::assertSame([$class, $kind, $rate], [$taxes[$name]['tax_class'], $taxes[$name]['kind'], $taxes[$name]['rate']], $name);
            self::assertTrue($taxes[$name]['standard'], "$name comes from the seed.");
            self::assertSame('Impoconsumo por valor' !== $name, $taxes[$name]['active'], 'Active, except the impoconsumo por valor, which waits for its value.');
        }
        self::assertSame('per_unit', $taxes['Impoconsumo por valor']['calculation']);
        $ninguno = array_filter($this->getJson('/api/v1/taxes?all=1')['items'], static fn (array $t) => 'Ninguno' === $t['name']);
        self::assertSame(['charge', 'withholding'], array_column($ninguno, 'tax_class'), 'Ninguno exists as a charge and as a withholding, so a line can name it.');
        self::assertCount(13, $this->getJson('/api/v1/taxes?all=1')['items'], 'Eleven taxes and the two Ninguno; ReteICA is company-defined.');
        self::assertArrayNotHasKey('ReteICA', $taxes);
    }

    public function testTheSeededTaxesPointAtTheSeededChart(): void
    {
        $company = $this->signUpOwner();

        $iva = $this->taxesByName()['IVA 19 %'];
        self::assertSame($this->account($company, '240805'), $iva['sales_account_id'], 'The chart is provisioned before the taxes (priority 100 before 50): IVA generado posts to 240805.');
        self::assertSame($this->account($company, '240810'), $iva['purchase_account_id'], 'IVA descontable posts to 240810.');
    }

    public function testEmptyValidityDatesMeanAlwaysValidNotToday(): void
    {
        $this->signUpOwner();

        $created = $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload(['valid_from' => '', 'valid_to' => '']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame([null, null], [$created['valid_from'], $created['valid_to']], 'An empty date is no date: the tax is always valid.');
    }

    public function testTheAccountantCreatesATaxWithItsAccountsAndValidityDates(): void
    {
        $company = $this->signUpOwner();
        $sales = $this->account($company, '240805', 'IVA generado');
        $purchases = $this->account($company, '240810', 'IVA descontable');
        $this->signInAs(Role::Accountant, $company);

        $created = $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload(['sales_account_id' => $sales, 'purchase_account_id' => $purchases, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['IVA 16 %', '16.0000', $sales, $purchases, '2026-01-01', '2026-12-31', true, false], [$created['name'], $created['rate'], $created['sales_account_id'], $created['purchase_account_id'], $created['valid_from'], $created['valid_to'], $created['active'], $created['standard']], 'Created active, not from the seed, rate as a decimal string.');
        self::assertArrayHasKey('IVA 16 %', $this->taxesByName());
    }

    public function testBillingUsersReadTaxesButCannotChangeThem(): void
    {
        $company = $this->signUpOwner();
        $id = $this->taxesByName()['IVA 5 %']['id'];
        $this->signInAs(Role::Billing, $company);

        $this->getJson('/api/v1/taxes');
        self::assertResponseIsSuccessful('Everyone reads the catalog.');
        $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload());
        self::assertResponseStatusCodeSame(403, 'Only the owner and the accountant edit taxes.');
        $this->sendJson('PUT', "/api/v1/taxes/$id", $this->ivaPayload());
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('POST', "/api/v1/taxes/$id/deactivate", []);
        self::assertResponseStatusCodeSame(403);
        $this->sendJson('DELETE', "/api/v1/taxes/$id", []);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSignedOutIsRefused(): void
    {
        $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload());

        self::assertResponseStatusCodeSame(401);
    }

    public function testEditingATaxChangesItsRateNameAndAccounts(): void
    {
        $company = $this->signUpOwner();
        $account = $this->account($company, '240805');
        $tax = $this->taxesByName()['IVA 5 %'];

        $updated = $this->sendJson('PUT', '/api/v1/taxes/'.$tax['id'], ['name' => 'IVA 6 %', 'calculation' => 'percentage', 'rate' => '6', 'sales_account_id' => $account, 'purchase_account_id' => null, 'valid_from' => '2027-01-01', 'valid_to' => null]);

        self::assertResponseIsSuccessful();
        self::assertSame(['IVA 6 %', '6.0000', $account, '2027-01-01', 'iva', 'charge'], [$updated['name'], $updated['rate'], $updated['sales_account_id'], $updated['valid_from'], $updated['kind'], $updated['tax_class']], 'Name, rate, accounts and dates change; the class and kind never do.');
        self::assertCount(1, $this->audit($company, 'tax.updated'), 'The change is in the audit log.');
        $row = $this->audit($company, 'tax.updated')[0];
        self::assertSame(['tax', $tax['id']], [$row->subjectType(), $row->subjectId()?->toRfc4122()], 'The row names the tax.');
        self::assertNotNull($row->userId(), 'The row names who changed it.');
        self::assertSame(['from', 'to'], array_keys($row->data()));
        self::assertSame(['name' => 'IVA 6 %', 'calculation' => 'percentage', 'rate' => '6.0000', 'sales_account_id' => $account, 'purchase_account_id' => null, 'valid_from' => '2027-01-01', 'valid_to' => null], $row->data()['to'], 'The data is the tax as it was left.');
        self::assertSame('IVA 5 %', $row->data()['from']['name']);
    }

    public function testRefusalsNameTheFieldAtFault(): void
    {
        $company = $this->signUpOwner();

        $cases = [
            'rate' => $this->ivaPayload(['rate' => '101']),
            'valid_to' => $this->ivaPayload(['valid_from' => '2026-05-02', 'valid_to' => '2026-05-01']),
            'kind' => $this->ivaPayload(['tax_class' => 'withholding']),
            'name' => $this->ivaPayload(['name' => 'iva 19 %']),
            'sales_account_id' => $this->ivaPayload(['sales_account_id' => '0197a7c0-0000-7000-8000-000000000000']),
        ];
        foreach ($cases as $field => $payload) {
            $body = $this->sendJson('POST', '/api/v1/taxes', $payload);
            self::assertResponseStatusCodeSame(422, "A bad $field is refused.");
            self::assertSame($field, $body['violations'][0]['field'], "The violation is on $field.");
        }
        $this->sendJson('POST', '/api/v1/taxes', ['name' => '']);
        self::assertResponseStatusCodeSame(422, 'A form with missing fields is refused.');
        self::assertCount(0, $this->audit($company, 'tax.created'), 'Nothing was created, nothing is logged.');
    }

    public function testAnotherCompanysAccountCannotBeUsed(): void
    {
        $other = $this->signUpOwner('b@b.co', '800197268', 'B');
        $foreign = $this->account($other, '240805');
        $this->signOut();
        $this->signUpOwner();

        $body = $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload(['sales_account_id' => $foreign]));

        self::assertResponseStatusCodeSame(422, 'An account of another company does not exist for this one.');
        self::assertSame('sales_account_id', $body['violations'][0]['field']);
    }

    public function testATaxOfAnotherCompanyIsNotFound(): void
    {
        $this->signUpOwner('b@b.co', '800197268', 'B');
        $foreign = $this->taxesByName()['IVA 19 %']['id'];
        $this->signOut();
        $this->signUpOwner();

        foreach ([['PUT', "/api/v1/taxes/$foreign", $this->ivaPayload(['name' => 'X', 'kind' => 'iva'])], ['POST', "/api/v1/taxes/$foreign/deactivate", []], ['POST', "/api/v1/taxes/$foreign/activate", []], ['DELETE', "/api/v1/taxes/$foreign", []]] as [$method, $uri, $payload]) {
            $this->sendJson($method, $uri, $payload);
            self::assertResponseStatusCodeSame(404, "$method $uri answers 404 for another company's tax.");
        }
        self::assertSame('19.0000', $this->taxesByName()['IVA 19 %']['rate'], 'And it was not touched.');
    }

    public function testDeactivatingHidesTheTaxFromTheDefaultListAndActivatingBringsItBack(): void
    {
        $company = $this->signUpOwner();
        $id = $this->taxesByName()['IVA 5 %']['id'];

        $off = $this->sendJson('POST', "/api/v1/taxes/$id/deactivate", []);
        self::assertResponseIsSuccessful();
        self::assertFalse($off['active']);
        self::assertNotContains('IVA 5 %', array_column($this->getJson('/api/v1/taxes')['items'], 'name'), 'Pickers do not offer an inactive tax.');

        $on = $this->sendJson('POST', "/api/v1/taxes/$id/activate", []);
        self::assertTrue($on['active']);
        self::assertContains('IVA 5 %', array_column($this->getJson('/api/v1/taxes')['items'], 'name'));
        self::assertCount(1, $this->audit($company, 'tax.deactivated'));
        self::assertCount(1, $this->audit($company, 'tax.activated'));
    }

    public function testAnUnusedTaxIsDeleted(): void
    {
        $company = $this->signUpOwner();
        $created = $this->sendJson('POST', '/api/v1/taxes', $this->ivaPayload());

        $this->sendJson('DELETE', '/api/v1/taxes/'.$created['id'], []);

        self::assertResponseStatusCodeSame(204);
        self::assertArrayNotHasKey('IVA 16 %', $this->taxesByName());
        $deleted = $this->audit($company, 'tax.deleted');
        self::assertCount(1, $deleted);
        self::assertSame('IVA 16 %', $deleted[0]->data()['name'], 'The log keeps what was deleted.');
    }

    public function testATaxUsedByADocumentCannotBeDeletedOnlyDeactivated(): void
    {
        $company = $this->signUpOwner();
        $tax = $this->taxesByName()['IVA 19 %'];
        $this->useTaxOnAQuotation($company, $tax['id']);

        $body = $this->sendJson('DELETE', '/api/v1/taxes/'.$tax['id'], []);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('tax_in_use', $body['error'], 'The error code the UI explains.');
        self::assertArrayHasKey('IVA 19 %', $this->taxesByName(), 'Still there.');
        $this->sendJson('POST', '/api/v1/taxes/'.$tax['id'].'/deactivate', []);
        self::assertResponseIsSuccessful('Deactivating is what a used tax allows.');
    }

    public function testATaxThatIsACompanyDefaultCannotBeDeleted(): void
    {
        $company = $this->signUpOwner();
        $tax = $this->taxesByName()['IVA 19 %'];
        $this->em()->getConnection()->executeStatement('UPDATE company SET default_charge_tax_id = ? WHERE id = ?', [Uuid::fromString($tax['id'])->toBinary(), $company->toBinary()]);

        $this->sendJson('DELETE', '/api/v1/taxes/'.$tax['id'], []);

        self::assertResponseStatusCodeSame(409, 'A default of the company points at it: deleting would break the company profile.');
    }

    public function testNingunoCannotBeChanged(): void
    {
        $this->signUpOwner();
        $none = $this->taxesByName()['Ninguno'];

        $this->sendJson('POST', '/api/v1/taxes/'.$none['id'].'/deactivate', []);
        self::assertResponseStatusCodeSame(422);
        $this->sendJson('DELETE', '/api/v1/taxes/'.$none['id'], []);
        self::assertResponseStatusCodeSame(422, 'Ninguno is what a line without a tax points at.');
    }

    public function testAReteIcaForAMunicipalityIsCreatedByTheCompany(): void
    {
        $this->signUpOwner();

        $created = $this->sendJson('POST', '/api/v1/taxes', ['name' => 'ReteICA Bogotá 0,966 %', 'tax_class' => 'withholding', 'kind' => 'reteica', 'calculation' => 'percentage', 'rate' => '0.9660']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([TaxClass::Withholding->value, TaxKind::IcaWithholding->value], [$created['tax_class'], $created['kind']]);
    }

    public function testTheSettingsListShowsAccountNamesAndWhatIsInUse(): void
    {
        $company = $this->signUpOwner();
        $account = $this->account($company, '240805', 'IVA generado');
        $iva = $this->taxesByName()['IVA 19 %'];
        $this->sendJson('PUT', '/api/v1/taxes/'.$iva['id'], ['name' => 'IVA 19 %', 'calculation' => 'percentage', 'rate' => '19', 'sales_account_id' => $account, 'purchase_account_id' => null, 'valid_from' => null, 'valid_to' => null]);
        $this->useTaxOnAQuotation($company, $iva['id']);
        $this->sendJson('POST', '/api/v1/taxes/'.$this->taxesByName()['IVA 5 %']['id'].'/deactivate', []);

        $items = $this->getJson('/api/v1/settings/taxes')['items'];

        self::assertResponseIsSuccessful();
        $byName = array_column($items, null, 'name');
        self::assertSame(['240805', 'IVA GENERADO', null], [$byName['IVA 19 %']['sales_account_code'], $byName['IVA 19 %']['sales_account_name'], $byName['IVA 19 %']['purchase_account_code']]);
        self::assertTrue($byName['IVA 19 %']['in_use'], 'A quotation line copied it.');
        self::assertFalse($byName['IVA por servicios 19 %']['in_use']);
        self::assertContains('IVA 5 %', array_column($items, 'name'), 'Inactive taxes are listed here: this is where they are reactivated.');
    }

    public function testBillingUsersReadTheSettingsListToo(): void
    {
        $company = $this->signUpOwner();
        $this->signInAs(Role::Billing, $company);

        $this->getJson('/api/v1/settings/taxes');

        self::assertResponseIsSuccessful();
    }
}
