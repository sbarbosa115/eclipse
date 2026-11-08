<?php

namespace App\Tests\Functional\Ledger;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * POST /accounts adding the first auxiliar under an account in use (observations PRD §4.1, §9 Q-H): the parent stops
 * receiving movements, and what pointed at it (posting rules, taxes, payment methods, products, terceros) moves to the
 * new auxiliar, audited, unless the accountant says not to.
 */
final class SubdivideAccountApiTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function add(string $parent, string $code, string $name = 'Nueva auxiliar', array $extra = []): array
    {
        $account = $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => $parent, 'code' => $code, 'name' => $name] + $extra);
        self::assertResponseStatusCodeSame(201, json_encode($account) ?: '');

        return $account;
    }

    /** A product whose revenue goes to $revenue (and expense to $expense). */
    private function product(string $code, string $revenue, ?string $expense = null): string
    {
        $product = $this->sendJson('POST', '/api/v1/products', [
            'type' => 'producto', 'code' => $code, 'name' => 'Producto '.$code, 'sale_price' => '10000', 'price_includes_tax' => false,
            'revenue_account_id' => $this->account($revenue)->toRfc4122(),
            'expense_account_id' => null === $expense ? null : $this->account($expense)->toRfc4122(),
        ]);
        self::assertResponseStatusCodeSame(201, json_encode($product) ?: '');

        return (string) $product['id'];
    }

    /** @return array<string, mixed> the posting rule of a concept */
    private function rule(string $concept): array
    {
        $rules = $this->getJson('/api/v1/posting-rules')['items'];

        return $rules[array_search($concept, array_column($rules, 'concept'), true)];
    }

    /** @return array<string, mixed> the tax by name on the settings tab */
    private function tax(string $name): array
    {
        $taxes = $this->getJson('/api/v1/settings/taxes')['items'];

        return $taxes[array_search($name, array_column($taxes, 'name'), true)];
    }

    /** @return list<array{action: string, data: array<string, mixed>}> */
    private function audit(string $action): array
    {
        $rows = $this->db()->fetchAllAssociative('SELECT action, data, user_id FROM audit_log WHERE company_id = ? AND action = ? ORDER BY id', [$this->company->toBinary(), $action]);
        foreach ($rows as $row) {
            self::assertNotNull($row['user_id'], 'Every move is the accountant\'s, not the system\'s.');
        }

        return array_map(static fn (array $r) => ['action' => (string) $r['action'], 'data' => (array) json_decode((string) $r['data'], true)], $rows);
    }

    private function column(string $table, string $column, string $id): ?string
    {
        $value = $this->db()->fetchOne("SELECT $column FROM $table WHERE id = ?", [Uuid::fromString($id)->toBinary()]);

        return \is_string($value) ? Uuid::fromBinary($value)->toRfc4122() : null;
    }

    public function testTheFirstAuxiliarTakesThePostingRuleAndTheProductsOfItsParent(): void
    {
        $this->signInAs('accountant');
        $moved = $this->product('P-1', '413595', '519595');
        $kept = $this->product('P-2', '413502');

        $child = $this->add('413595', '41359501', 'Ventas gravadas');

        self::assertSame(['posting_rules' => ['ingreso'], 'taxes' => 0, 'payment_methods' => 0, 'products' => 1, 'terceros' => 0], $child['moved'], 'The answer says what pointed at 413595 and moved.');
        self::assertSame(['41359501', true], [$this->rule('ingreso')['account_code'], $this->rule('ingreso')['account_postable']], 'Revenue now posts to the auxiliar.');
        self::assertSame($child['id'], $this->column('product', 'revenue_account_id', $moved));
        self::assertSame($this->account('519595')->toRfc4122(), $this->column('product', 'expense_account_id', $moved), 'Only the account that was subdivided moves.');
        self::assertSame($this->account('413502')->toRfc4122(), $this->column('product', 'revenue_account_id', $kept));

        self::assertSame([['action' => 'posting_rule.changed', 'data' => ['concept' => 'ingreso', 'from' => '413595', 'to' => '41359501', 'reason' => 'account_subdivided']]], $this->audit('posting_rule.changed'));
        self::assertSame([['action' => 'product.updated', 'data' => ['reason' => 'account_subdivided', 'from' => ['revenue_account' => '413595'], 'to' => ['revenue_account' => '41359501']]]], $this->audit('product.updated'));
    }

    public function testTheSecondAuxiliarMovesNothing(): void
    {
        $first = $this->add('413595', '41359501', 'Ventas gravadas');
        $second = $this->add('413595', '41359502', 'Ventas excluidas');

        self::assertNotNull($first['moved']);
        self::assertNull($second['moved'], 'The parent no longer took movements: nothing pointed at it.');
        self::assertSame('41359501', $this->rule('ingreso')['account_code'], 'The rule stays on the first auxiliar.');
        self::assertCount(1, $this->audit('posting_rule.changed'));
    }

    public function testTaxesMoveTheSideThatPointedAtTheParent(): void
    {
        $child = $this->add('240805', '24080501', 'IVA generado 19 %');

        self::assertSame(['posting_rules' => ['iva_generado'], 'taxes' => 4, 'payment_methods' => 0, 'products' => 0, 'terceros' => 0], $child['moved'], 'IVA 19 %, 5 %, 0 % and por servicios post their sales side to 240805.');
        $iva = $this->tax('IVA 19 %');
        self::assertSame(['24080501', '240810', true], [$iva['sales_account_code'], $iva['purchase_account_code'], $iva['account_postable']]);
        $audits = $this->audit('tax.updated');
        self::assertCount(4, $audits);
        self::assertSame('account_subdivided', $audits[0]['data']['reason']);
        self::assertSame([$this->account('240805')->toRfc4122(), $child['id']], [$audits[0]['data']['from']['sales_account_id'], $audits[0]['data']['to']['sales_account_id']]);
    }

    public function testPaymentMethodsAndTercerosMoveToo(): void
    {
        $tercero = $this->tercero('Client Abroad Inc.', receivable: '13051001', payable: '22050501');

        $cash = $this->add('11050501', '1105050101', 'Caja menor');
        $abroad = $this->add('13051001', '1305100101', 'Clientes en dólares');

        self::assertSame(['posting_rules' => [], 'taxes' => 0, 'payment_methods' => 1, 'products' => 0, 'terceros' => 0], $cash['moved'], 'Efectivo lands in 11050501.');
        $methods = $this->getJson('/api/v1/settings/payment-methods')['items'];
        $efectivo = $methods[array_search('Efectivo', array_column($methods, 'name'), true)];
        self::assertSame(['1105050101', true], [$efectivo['account_code'], $efectivo['account_postable']]);
        self::assertSame('account_subdivided', $this->audit('payment_method.updated')[0]['data']['reason']);

        self::assertSame(['posting_rules' => [], 'taxes' => 0, 'payment_methods' => 0, 'products' => 0, 'terceros' => 1], $abroad['moved']);
        self::assertSame($abroad['id'], $this->column('tercero', 'receivable_account_id', $tercero->toRfc4122()));
        self::assertSame($this->account('22050501')->toRfc4122(), $this->column('tercero', 'payable_account_id', $tercero->toRfc4122()));
        self::assertSame([['action' => 'tercero.updated', 'data' => ['reason' => 'account_subdivided', 'from' => ['receivable_account' => '13051001'], 'to' => ['receivable_account' => '1305100101']]]], $this->audit('tercero.updated'));
    }

    public function testWithoutMovingReferencesTheParentIsLeftForTheAccountantToFix(): void
    {
        $product = $this->product('P-1', '413595');

        $child = $this->add('413595', '41359501', 'Ventas gravadas', ['move_references' => false]);

        self::assertNull($child['moved'], 'Nothing moved.');
        self::assertSame(['413595', false], [$this->rule('ingreso')['account_code'], $this->rule('ingreso')['account_postable']], 'The rule still names 413595, which no longer takes movements.');
        self::assertSame($this->account('413595')->toRfc4122(), $this->column('product', 'revenue_account_id', $product));
        self::assertSame([], $this->audit('posting_rule.changed'));
        self::assertSame([], $this->audit('product.updated'));

        $this->sendJson('PUT', '/api/v1/posting-rules/ingreso', ['account_id' => $this->account('413595')->toRfc4122()]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['account_has_auxiliaries', ['account_code' => '413595']], [$this->body()['error'], $this->body()['detail']], 'A rule cannot be pointed back at it either.');
    }

    public function testATaxCannotBePointedAtASubdividedAccount(): void
    {
        $this->add('240805', '24080501', 'IVA generado 19 %');
        $iva = $this->tax('IVA 19 %');

        $body = $this->sendJson('PUT', '/api/v1/taxes/'.$iva['id'], [
            'name' => $iva['name'], 'calculation' => $iva['calculation'], 'rate' => $iva['rate'],
            'sales_account_id' => $this->account('240805')->toRfc4122(), 'purchase_account_id' => $iva['purchase_account_id'],
            'valid_from' => null, 'valid_to' => null,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('sales_account_id', $body['violations'][0]['field'] ?? null, json_encode($body) ?: '');
    }

    public function testAnotherCompanysReferencesNeverMove(): void
    {
        $mine = $this->company;
        $this->signOut();
        $other = $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');
        $theirs = $this->product('B-1', '413595');
        $theirRevenue = $this->account('413595')->toRfc4122();
        $this->signOut();
        $this->company = $mine;
        $this->signIn('ana@acme.co');

        $child = $this->add('413595', '41359501', 'Ventas gravadas');

        self::assertSame(0, $child['moved']['products']);
        self::assertSame($theirRevenue, $this->column('product', 'revenue_account_id', $theirs));
        $this->company = $other;
        self::assertSame([], $this->audit('posting_rule.changed'), 'B\'s rule still posts to its own 413595.');
    }

    public function testTheBillingUserCannotSubdivide(): void
    {
        $this->signInAs('billing');

        $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => '413595', 'code' => '41359501', 'name' => 'Ventas gravadas']);

        self::assertResponseStatusCodeSame(403);
    }
}
