<?php

namespace App\Tests\Functional\Party;

use App\Access\Application\Port\PasswordHasher;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use App\Sales\Domain\Model\Quotation;
use App\Shared\Domain\Model\AuditLog;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class TerceroApiTest extends ApiTestCase
{
    use SignsUp;

    private const NIT = '800197268';

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return $override + [
            'person_type' => 'empresa',
            'identification_type' => 'nit',
            'identification_number' => self::NIT,
            'business_name' => 'Distribuciones Andina S.A.S.',
            'trade_name' => 'Andina',
            'city' => 'Bogotá',
            'address' => 'Calle 1 # 2-3',
            'phones' => [['indicative' => '57', 'number' => '6011234567', 'extension' => '12']],
            'billing_contact_name' => 'Laura Gómez',
            'email' => 'facturas@andina.co',
            'mobile' => '3001234567',
            'postal_code' => '110111',
            'vat_regime' => 'responsable',
            'billing_contact_is_payer' => true,
            'fiscal_responsibilities' => ['O-13', 'O-15'],
            'roles' => ['cliente', 'proveedor'],
            'contacts' => [['name' => 'Pedro Ruiz', 'email' => 'pedro@andina.co', 'phone' => '3109876543']],
        ];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function create(array $override = []): array
    {
        $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload($override));
        self::assertResponseStatusCodeSame(201, 'The full form creates a tercero: '.json_encode($body));

        return $body;
    }

    /** @param array<mixed> $session */
    private function companyId(array $session): Uuid
    {
        return Uuid::fromString($session['company_id']);
    }

    /** Signs in a second user of the company with this role (the invitation flow is the "access" item's). */
    private function signInAs(Role $role, Uuid $companyId): void
    {
        $this->signOut();
        $email = $role->value.'@acme.co';
        $user = User::invite($companyId, $email, ucfirst($role->value), $role, new \DateTimeImmutable());
        $hash = static::getContainer()->get(PasswordHasher::class)->hash('correct horse battery');
        (function () use ($hash): void {
            $this->passwordHash = $hash;
            $this->status = UserStatus::Active;
        })->call($user);
        $this->save($user);
        $this->signIn($email);
    }

    public function testTheFullFormCreatesATerceroWithEveryFieldGroup(): void
    {
        $this->signUp();
        $t = $this->create();

        self::assertSame('Distribuciones Andina S.A.S.', $t['display_name'], 'A company shows its razón social.');
        self::assertSame('4', $t['check_digit'], 'The DV of a NIT is computed when it is not sent.');
        self::assertSame('0', $t['branch_code'], 'The branch code defaults to 0.');
        self::assertSame([['indicative' => '57', 'number' => '6011234567', 'extension' => '12']], $t['phones']);
        self::assertSame(['O-13', 'O-15'], $t['fiscal_responsibilities']);
        self::assertSame(['cliente', 'proveedor'], $t['roles'], 'A tercero holds several roles.');
        self::assertSame('Pedro Ruiz', $t['contacts'][0]['name']);
        self::assertTrue($t['billing_contact_is_payer']);
        self::assertNull($t['erased_at']);

        $read = $this->getJson('/api/v1/terceros/'.$t['id']);
        self::assertResponseIsSuccessful();
        self::assertSame($t, $read, 'Reading it back gives what creating answered.');
    }

    public function testThePersonsNameIsBuiltFromNombresAndApellidos(): void
    {
        $this->signUp();
        $t = $this->create(['person_type' => 'persona', 'identification_type' => 'cc', 'identification_number' => '1.020.304.050', 'business_name' => null, 'first_names' => 'Ana María', 'last_names' => 'Pérez Soto', 'check_digit' => '7']);

        self::assertSame('Ana María Pérez Soto', $t['display_name']);
        self::assertSame('1020304050', $t['identification_number'], 'Dots are not part of the number.');
        self::assertNull($t['check_digit'], 'Only a NIT carries a DV.');
    }

    public function testTheDvOfANitIsEditable(): void
    {
        $this->signUp();

        self::assertSame('9', $this->create(['check_digit' => '9'])['check_digit']);
    }

    public function testRequiredFieldsAreReportedOnTheirField(): void
    {
        $this->signUp();
        $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload(['business_name' => null, 'roles' => []]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);
        self::assertContains($body['violations'][0]['field'], ['business_name', 'roles']);

        $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload(['roles' => []]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('roles', $body['violations'][0]['field'], 'A tercero needs at least one role.');

        $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload(['email' => 'not-an-email']));
        self::assertSame('email', $body['violations'][0]['field']);
    }

    public function testTheSameIdentificationTwiceIsRefusedOnTheField(): void
    {
        $this->signUp();
        $this->create();
        $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload(['identification_number' => '800.197.268']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('duplicate_identification', $body['error']);
        self::assertSame('identification_number', $body['violations'][0]['field'], 'The form shows it next to the number.');
        self::assertSame('Ya existe un tercero con esta identificación.', $body['violations'][0]['message']);
    }

    public function testAnotherBranchOfTheSameIdentificationIsAllowed(): void
    {
        $this->signUp();
        $this->create();
        $branch = $this->create(['branch_code' => '1']);

        self::assertSame('1', $branch['branch_code'], 'Tipo + número + sucursal is the key.');
    }

    public function testTwoCompaniesMayHoldTheSameIdentification(): void
    {
        $this->signUp('beto@b.co', '890903938', 'B');
        $this->create();
        $this->signOut();
        $this->signUp();

        self::assertNotEmpty($this->create()['id'], 'Uniqueness is per company.');
    }

    public function testUpdateReplacesTheDataAndKeepsContactsById(): void
    {
        $this->signUp();
        $t = $this->create();
        $contactId = $t['contacts'][0]['id'];

        $updated = $this->sendJson('PUT', '/api/v1/terceros/'.$t['id'], $this->payload([
            'business_name' => 'Andina Holding',
            'roles' => ['proveedor'],
            'fiscal_responsibilities' => [],
            'phones' => [],
            'contacts' => [['id' => $contactId, 'name' => 'Pedro R.', 'email' => null, 'phone' => null], ['name' => 'Nuevo']],
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame('Andina Holding', $updated['display_name']);
        self::assertSame(['proveedor'], $updated['roles']);
        self::assertSame(['R-99-PN'], $updated['fiscal_responsibilities'], 'R-99-PN is the default responsibility.');
        self::assertSame([], $updated['phones']);
        self::assertCount(2, $updated['contacts']);
        self::assertContains($contactId, array_column($updated['contacts'], 'id'), 'A contact sent with its id keeps it, since documents point at it.');
    }

    public function testUpdatingToAnotherTercerosIdentificationIsRefused(): void
    {
        $this->signUp();
        $this->create();
        $second = $this->create(['identification_number' => '890903938']);
        $body = $this->sendJson('PUT', '/api/v1/terceros/'.$second['id'], $this->payload());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('duplicate_identification', $body['error']);
    }

    public function testSavingATerceroWithItsOwnIdentificationIsNotADuplicate(): void
    {
        $this->signUp();
        $t = $this->create();
        $this->sendJson('PUT', '/api/v1/terceros/'.$t['id'], $this->payload(['city' => 'Medellín']));

        self::assertResponseIsSuccessful();
    }

    public function testAccountOverridesMustBePostableAccountsOfTheCompany(): void
    {
        $company = $this->companyId($this->signUp());
        $this->signOut();
        $other = $this->companyId($this->signUp('beto@b.co', '890903938', 'B'));
        $receivable = new Account($company, '130505', 'Nacionales', AccountNature::Debit, '1305', true);
        $payable = new Account($company, '220505', 'Proveedores nacionales', AccountNature::Credit, '2205', true);
        $group = new Account($company, '1305', 'Clientes', AccountNature::Debit, '13', true);
        $foreign = new Account($other, '130505', 'Nacionales de B', AccountNature::Debit, '1305', true);
        $this->save($receivable, $payable, $group, $foreign);
        $this->signOut();
        $this->signIn('ana@acme.co');

        $ok = $this->create(['receivable_account_id' => $receivable->id()->toRfc4122(), 'payable_account_id' => $payable->id()->toRfc4122()]);
        self::assertSame('130505', $ok['receivable_account']['code']);
        self::assertSame('Proveedores nacionales', $ok['payable_account']['name']);

        foreach ([[$group, 'receivable_account_id'], [$foreign, 'receivable_account_id'], [$payable, 'receivable_account_id'], [$receivable, 'payable_account_id']] as [$account, $field]) {
            $body = $this->sendJson('POST', '/api/v1/terceros', $this->payload(['identification_number' => '890903938', $field => $account->id()->toRfc4122()]));
            self::assertResponseStatusCodeSame(422, $account->code().' is not valid for '.$field);
            self::assertSame($field, $body['violations'][0]['field']);
        }
    }

    public function testQuickCreateNeedsOnlyTheEssentials(): void
    {
        $this->signUp();
        $body = $this->sendJson('POST', '/api/v1/terceros/quick', [
            'person_type' => 'empresa',
            'identification_type' => 'nit',
            'identification_number' => self::NIT,
            'business_name' => 'Cliente Rápido S.A.S.',
            'email' => 'rapido@cliente.co',
            'roles' => ['cliente'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Cliente Rápido S.A.S.', $body['display_name']);
        self::assertSame('4', $body['check_digit']);
        self::assertSame(['cliente'], $body['roles']);
        self::assertTrue($body['active']);

        $this->sendJson('POST', '/api/v1/terceros/quick', ['person_type' => 'empresa', 'identification_type' => 'nit', 'identification_number' => '890903938', 'business_name' => 'X', 'roles' => ['cliente']]);
        self::assertResponseStatusCodeSame(422, 'The e-mail is required to quick-create.');

        $this->sendJson('POST', '/api/v1/terceros/quick', ['person_type' => 'empresa', 'identification_type' => 'nit', 'identification_number' => self::NIT, 'business_name' => 'X', 'email' => 'x@x.co', 'roles' => ['cliente']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testListSearchesNameAndIdentificationAndFilters(): void
    {
        $this->signUp();
        $this->create();
        $this->create(['identification_number' => '890903938', 'business_name' => 'Banco 100% Fiable', 'trade_name' => null, 'roles' => ['otro']]);
        $inactive = $this->create(['identification_number' => '899999068', 'business_name' => 'Ecopetrol', 'trade_name' => null, 'roles' => ['proveedor']]);
        $this->sendJson('POST', '/api/v1/terceros/'.$inactive['id'].'/deactivate', []);

        $all = $this->getJson('/api/v1/terceros');
        self::assertSame(3, $all['total']);
        self::assertSame(['Banco 100% Fiable', 'Distribuciones Andina S.A.S.', 'Ecopetrol'], array_column($all['items'], 'display_name'), 'Listed by name.');
        self::assertSame(1, $all['page']);

        self::assertSame(['Distribuciones Andina S.A.S.'], array_column($this->getJson('/api/v1/terceros?q=andina')['items'], 'display_name'), 'Part of the name matches.');
        self::assertSame(['Distribuciones Andina S.A.S.'], array_column($this->getJson('/api/v1/terceros?q=800.197')['items'], 'display_name'), 'Part of the identification matches, punctuation aside.');
        self::assertSame(['Banco 100% Fiable'], array_column($this->getJson('/api/v1/terceros?q='.urlencode('100%'))['items'], 'display_name'));
        self::assertSame([], $this->getJson('/api/v1/terceros?q='.urlencode('%Eco'))['items'] ?? [], 'A percent sign is not a wildcard.');
        self::assertSame(0, $this->getJson('/api/v1/terceros?q='.urlencode('A_dina'))['total'], 'An underscore is not a wildcard.');
        self::assertSame(['Banco 100% Fiable'], array_column($this->getJson('/api/v1/terceros?role=otro')['items'], 'display_name'));
        self::assertSame(['Distribuciones Andina S.A.S.', 'Ecopetrol'], array_column($this->getJson('/api/v1/terceros?role=proveedor')['items'], 'display_name'));
        self::assertSame(['Banco 100% Fiable', 'Distribuciones Andina S.A.S.'], array_column($this->getJson('/api/v1/terceros?active=1')['items'], 'display_name'));
        self::assertSame(['Ecopetrol'], array_column($this->getJson('/api/v1/terceros?active=0')['items'], 'display_name'));

        $page = $this->getJson('/api/v1/terceros?per_page=2&page=2');
        self::assertSame(3, $page['total']);
        self::assertSame(['Ecopetrol'], array_column($page['items'], 'display_name'), 'The second page of two holds the third.');
        self::assertSame(2, $page['per_page']);
    }

    public function testASearchOnlySeesTheSignedInCompanysTerceros(): void
    {
        $this->signUp('beto@b.co', '890903938', 'B');
        $theirs = $this->create();
        $this->signOut();
        $this->signUp();
        $this->create(['identification_number' => '890903938', 'business_name' => 'Mío']);

        self::assertSame(['Mío'], array_column($this->getJson('/api/v1/terceros?q=')['items'], 'display_name'));
        $this->getJson('/api/v1/terceros/'.$theirs['id']);
        self::assertResponseStatusCodeSame(404, 'Another company\'s id answers 404, not 403.');
        $this->sendJson('PUT', '/api/v1/terceros/'.$theirs['id'], $this->payload());
        self::assertResponseStatusCodeSame(404);
        $this->sendJson('POST', '/api/v1/terceros/'.$theirs['id'].'/deactivate', []);
        self::assertResponseStatusCodeSame(404);
        $this->sendJson('POST', '/api/v1/terceros/'.$theirs['id'].'/erase', []);
        self::assertResponseStatusCodeSame(404);
        $this->getJson('/api/v1/terceros/'.$theirs['id'].'/export');
        self::assertResponseStatusCodeSame(404);
        $this->getJson('/api/v1/terceros/'.$theirs['id'].'/contacts');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/api/v1/terceros/'.$theirs['id']);
        self::assertResponseStatusCodeSame(404);
        $this->getJson('/api/v1/terceros/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testContactsAreListedForTheDocumentHeader(): void
    {
        $this->signUp();
        $t = $this->create();
        $items = $this->getJson('/api/v1/terceros/'.$t['id'].'/contacts')['items'];

        self::assertResponseIsSuccessful();
        self::assertSame(['Pedro Ruiz'], array_column($items, 'name'));
        self::assertSame('pedro@andina.co', $items[0]['email']);
    }

    public function testDeactivateAndReactivate(): void
    {
        $this->signUp();
        $t = $this->create();

        self::assertFalse($this->sendJson('POST', '/api/v1/terceros/'.$t['id'].'/deactivate', [])['active']);
        self::assertTrue($this->sendJson('POST', '/api/v1/terceros/'.$t['id'].'/reactivate', [])['active']);
    }

    public function testATerceroNoDocumentNamesCanBeDeleted(): void
    {
        $this->signUp();
        $t = $this->create();
        $this->client->request('DELETE', '/api/v1/terceros/'.$t['id']);

        self::assertResponseStatusCodeSame(204);
        $this->getJson('/api/v1/terceros/'.$t['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testATerceroADocumentNamesCannotBeDeletedOnlyDeactivated(): void
    {
        $session = $this->signUp();
        $t = $this->create();
        $this->save(new Quotation($this->companyId($session), Uuid::fromString($t['id']), $t['display_name'], new \DateTimeImmutable('today'), new \DateTimeImmutable('+30 days'), Uuid::fromString($session['user_id']), new \DateTimeImmutable()));

        $body = $this->sendJson('DELETE', '/api/v1/terceros/'.$t['id'], []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('tercero_in_use', $body['error']);

        self::assertFalse($this->sendJson('POST', '/api/v1/terceros/'.$t['id'].'/deactivate', [])['active'], 'Deactivating is what is left to do.');
    }

    public function testExportGivesThePersonalDataAndIsAudited(): void
    {
        $session = $this->signUp();
        $t = $this->create();
        $body = $this->getJson('/api/v1/terceros/'.$t['id'].'/export');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame('facturas@andina.co', $body['tercero']['email']);
        self::assertSame('Pedro Ruiz', $body['tercero']['contacts'][0]['name']);
        self::assertNotEmpty($body['exported_at']);

        $log = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findOneBy(['action' => 'tercero.personal_data_exported']);
        self::assertNotNull($log, 'An export of personal data leaves a trace.');
        self::assertSame($session['user_id'], $log->userId()?->toRfc4122());
    }

    public function testEraseBlanksPersonalDataAndKeepsTheRow(): void
    {
        $this->signUp();
        $t = $this->create(['person_type' => 'persona', 'identification_type' => 'cc', 'identification_number' => '1020304050', 'business_name' => null, 'first_names' => 'Ana', 'last_names' => 'Pérez']);
        $erased = $this->sendJson('POST', '/api/v1/terceros/'.$t['id'].'/erase', []);

        self::assertResponseIsSuccessful();
        self::assertNotNull($erased['erased_at']);
        self::assertSame($t['id'], $erased['id'], 'The row stays: documents name it.');
        self::assertSame('1020304050', $erased['identification_number'], 'The identification stays, since invoices carry it.');
        foreach (['first_names', 'last_names', 'email', 'mobile', 'address', 'city', 'billing_contact_name', 'postal_code', 'trade_name'] as $field) {
            self::assertNull($erased[$field], "$field is blanked.");
        }
        self::assertSame([], $erased['phones']);
        self::assertSame([], $erased['contacts']);
        self::assertFalse($erased['active'], 'An erased tercero is deactivated.');
        self::assertNotSame('Ana Pérez', $erased['display_name']);

        $body = $this->sendJson('PUT', '/api/v1/terceros/'.$t['id'], $this->payload());
        self::assertResponseStatusCodeSame(422, 'There is nothing left to edit.');
        self::assertSame('tercero_erased', $body['error']);
        $this->sendJson('POST', '/api/v1/terceros/'.$t['id'].'/reactivate', []);
        self::assertResponseStatusCodeSame(422);

        $log = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AuditLog::class)->findOneBy(['action' => 'tercero.personal_data_erased']);
        self::assertNotNull($log, 'An erasure leaves a trace.');
    }

    public function testBillingUsersWriteAndAccountantsOnlyRead(): void
    {
        $session = $this->signUp();
        $company = $this->companyId($session);
        $t = $this->create();

        $this->signInAs(Role::Billing, $company);
        $second = $this->create(['identification_number' => '890903938', 'business_name' => 'Hecho por facturación']);
        self::assertNotEmpty($second['id'], 'A billing user creates terceros.');
        $this->sendJson('PUT', '/api/v1/terceros/'.$second['id'], $this->payload(['identification_number' => '890903938']));
        self::assertResponseIsSuccessful('and edits them.');

        $this->signInAs(Role::Accountant, $company);
        self::assertSame(2, $this->getJson('/api/v1/terceros')['total'], 'The accountant reads the list.');
        $this->getJson('/api/v1/terceros/'.$t['id']);
        self::assertResponseIsSuccessful('and one tercero.');
        $this->getJson('/api/v1/terceros/'.$t['id'].'/contacts');
        self::assertResponseIsSuccessful();

        $write = [
            ['POST', '/api/v1/terceros', $this->payload(['identification_number' => '899999068'])],
            ['POST', '/api/v1/terceros/quick', ['identification_number' => '899999068', 'business_name' => 'X', 'email' => 'x@x.co', 'roles' => ['cliente']]],
            ['PUT', '/api/v1/terceros/'.$t['id'], $this->payload()],
            ['POST', '/api/v1/terceros/'.$t['id'].'/deactivate', []],
            ['POST', '/api/v1/terceros/'.$t['id'].'/reactivate', []],
            ['POST', '/api/v1/terceros/'.$t['id'].'/erase', []],
            ['DELETE', '/api/v1/terceros/'.$t['id'], []],
        ];
        foreach ($write as [$method, $uri, $payload]) {
            $this->sendJson($method, $uri, $payload);
            self::assertResponseStatusCodeSame(403, "The accountant may not $method $uri.");
        }
        $this->getJson('/api/v1/terceros/'.$t['id'].'/export');
        self::assertResponseStatusCodeSame(403, 'Exporting personal data is not a read for the accountant.');
    }

    public function testSignedOutIsRefused(): void
    {
        $this->getJson('/api/v1/terceros');
        self::assertResponseStatusCodeSame(401);
        $this->sendJson('POST', '/api/v1/terceros/quick', []);
        self::assertResponseStatusCodeSame(401);
    }
}
