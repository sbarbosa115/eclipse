<?php

namespace App\Tests\Functional\Ledger;

use App\Tests\Support\ApiTestCase;

/**
 * §4.13 Plan de cuentas: browse the chart and, as the accountant (or the owner), add sub-accounts and auxiliares,
 * rename the company's own and deactivate unused ones (§4.1).
 */
final class ChartApiTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /** @return array<mixed> */
    private function add(string $parent, string $code, string $name = 'Nueva cuenta', ?bool $purchases = null): array
    {
        return $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => $parent, 'code' => $code, 'name' => $name] + (null === $purchases ? [] : ['usable_on_purchases' => $purchases]));
    }

    /** @return array<mixed> */
    private function update(string $code, string $name, bool $active = true, bool $purchases = false): array
    {
        return $this->sendJson('PUT', '/api/v1/accounts/'.$this->account($code)->toRfc4122(), ['name' => $name, 'active' => $active, 'usable_on_purchases' => $purchases]);
    }

    public function testTheChartIsBrowsedByPagesClassAndSearch(): void
    {
        $page = $this->getJson('/api/v1/accounts?per_page=10');
        self::assertResponseIsSuccessful();
        self::assertSame(['1', '11', '1105', '110505', '11050501', '110510', '110515', '1110', '111005', '11100501'], array_column($page['items'], 'code'), 'In code order, each account before its children.');
        self::assertSame([1, 10], [$page['page'], $page['per_page']]);
        self::assertGreaterThan(2500, $page['total']);
        self::assertSame(['id', 'code', 'name', 'nature', 'level', 'parent_code', 'standard', 'active', 'postable', 'usable_on_purchases'], array_keys($page['items'][0]));

        self::assertSame(['1105', '110505', '11050501', '110510', '110515'], array_column($this->getJson('/api/v1/accounts?q=1105')['items'], 'code'), 'A number is a code prefix.');
        $byName = array_column($this->getJson('/api/v1/accounts?q=iva%20gen')['items'], 'code');
        self::assertSame(['240805'], $byName, 'Words search the name.');
        $classFive = $this->getJson('/api/v1/accounts?class=5&per_page=100');
        self::assertSame([], array_filter(array_column($classFive['items'], 'code'), static fn (string $c) => '5' !== $c[0]));
        self::assertSame('111010', $this->getJson('/api/v1/accounts?per_page=10&page=2')['items'][0]['code'], 'Later pages go on in code order.');
        self::assertSame(0, $this->getJson('/api/v1/accounts?q=%25')['total'], '% matches itself, not everything.');
    }

    public function testTheAccountantAddsAnAuxiliarUnderAPucSubcuenta(): void
    {
        $this->signInAs('accountant');

        $account = $this->add('111005', '11100502', 'Bancolombia ahorros');

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['11100502', 'Bancolombia ahorros', 'debit', 'auxiliary', '111005', false, true, true, false], [
            $account['code'], $account['name'], $account['nature'], $account['level'], $account['parent_code'], $account['standard'], $account['active'], $account['postable'], $account['usable_on_purchases'],
        ]);
        self::assertSame(['11100502'], array_column($this->getJson('/api/v1/accounts/search?q=11100502')['items'], 'code'), 'Documents can pick it right away.');
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_log WHERE company_id = ? AND action = 'account.created'", [$this->company->toBinary()]));
        $row = $this->db()->fetchAssociative("SELECT subject_type, data, user_id IS NOT NULL AS by_someone FROM audit_log WHERE company_id = ? AND action = 'account.created'", [$this->company->toBinary()]);
        self::assertSame(['ledger_account', ['code' => '11100502', 'name' => 'Bancolombia ahorros'], 1], [$row['subject_type'] ?? null, json_decode((string) ($row['data'] ?? ''), true), (int) ($row['by_someone'] ?? 0)]);
    }

    public function testAnExpenseAccountIsUsableOnPurchasesUnlessSaidOtherwise(): void
    {
        self::assertTrue($this->add('5135', '513596', 'Aseo externo')['usable_on_purchases']);
        self::assertFalse($this->add('5135', '513597', 'Interno', purchases: false)['usable_on_purchases']);
        self::assertTrue($this->add('2335', '233596', 'Otros por pagar', purchases: true)['usable_on_purchases'], '§9 Q14: any account the accountant marks.');
    }

    public function testACodeIsRefusedWhenTakenOrNotUnderItsParent(): void
    {
        $this->add('110505', '11050501');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('account_code_taken', $this->body()['error']);

        $this->add('111005', '1110');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('account_code_invalid', $this->body()['error']);

        $this->add('11', '1199');
        self::assertResponseStatusCodeSame(422, 'Companies add sub-accounts and auxiliares, not cuentas.');

        $this->add('999999', '99999901');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('parent_account_not_found', $this->body()['error']);

        $body = $this->add('111005', '11100503', '');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('name', $body['violations'][0]['field']);
    }

    public function testTheCompanysOwnAccountIsRenamedAndDeactivated(): void
    {
        $this->add('111005', '11100502', 'Banco viejo');

        $account = $this->update('11100502', 'Banco cerrado', active: false);

        self::assertResponseIsSuccessful();
        self::assertSame(['Banco cerrado', false, false], [$account['name'], $account['active'], $account['postable']]);
        self::assertSame([], $this->getJson('/api/v1/accounts/search?q=11100502')['items'], 'An inactive account is not offered on documents.');
        self::assertSame(['11100502'], array_column($this->getJson('/api/v1/accounts?q=11100502')['items'], 'code'), 'The chart still lists it.');
    }

    public function testAPucAccountKeepsItsNameButMayBeDeactivated(): void
    {
        $this->update('110510', 'Cajas chicas');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('account_standard', $this->body()['error']);

        self::assertFalse($this->update('110510', 'CAJAS MENORES', active: false)['active']);
        self::assertTrue($this->update('513505', 'ASEO Y VIGILANCIA', purchases: true)['usable_on_purchases']);
    }

    public function testAnAccountAPostingRuleUsesStaysActive(): void
    {
        $this->update('13050501', 'CLIENTES NACIONALES', active: false);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('account_in_posting_rule', $this->body()['error']);
    }

    public function testABillingUserReadsTheChartButDoesNotEditIt(): void
    {
        $this->signInAs('billing');

        $this->getJson('/api/v1/accounts');
        self::assertResponseIsSuccessful();
        $this->add('111005', '11100502');
        self::assertResponseStatusCodeSame(403);
        $this->update('11050501', 'Caja');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnotherCompanysAccountIsNotFound(): void
    {
        $this->signOut();
        $theirs = $this->account('11050501', $this->startCompany('beto@b.co', '800197268', 'B S.A.S.'));
        $this->signOut();
        $this->signIn('ana@acme.co');

        $this->sendJson('PUT', '/api/v1/accounts/'.$theirs->toRfc4122(), ['name' => 'Mía', 'active' => false, 'usable_on_purchases' => false]);

        self::assertResponseStatusCodeSame(404);
        self::assertNotContains($theirs->toRfc4122(), array_column($this->getJson('/api/v1/accounts?q=11050501')['items'], 'id'));
    }

    public function testSignedOutIsRefused(): void
    {
        $this->signOut();
        $this->getJson('/api/v1/accounts');

        self::assertResponseStatusCodeSame(401);
    }
}
