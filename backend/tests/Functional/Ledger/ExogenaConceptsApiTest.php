<?php

namespace App\Tests\Functional\Ledger;

use App\Tests\Support\ApiTestCase;

/**
 * GET/PUT /ledger/exogena-concepts (observations PRD §4.7 d, §6): which DIAN concept an account's movements report under
 * in formats 1001 and 1007. Seeded with the obvious defaults for every company; the owner and the accountant replace the
 * list, and each change is written to the audit log.
 */
final class ExogenaConceptsApiTest extends ApiTestCase
{
    use LedgerFixtures;

    /** The defaults of PRD §4.7 d, by format then prefix. */
    private const DEFAULTS = [
        ['format' => '1001', 'account_prefix' => '1435', 'concept_code' => '5007'],
        ['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5001'],
        ['format' => '1001', 'account_prefix' => '5120', 'concept_code' => '5004'],
        ['format' => '1001', 'account_prefix' => '5135', 'concept_code' => '5003'],
        ['format' => '1001', 'account_prefix' => '5195', 'concept_code' => '5016'],
        ['format' => '1001', 'account_prefix' => '6205', 'concept_code' => '5007'],
        ['format' => '1007', 'account_prefix' => '41', 'concept_code' => '4001'],
        ['format' => '1007', 'account_prefix' => '42', 'concept_code' => '4002'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testANewCompanyStartsWithTheDefaults(): void
    {
        self::assertSame(['items' => self::DEFAULTS], $this->getJson('/api/v1/ledger/exogena-concepts'), 'Seeded at sign-up, by format then prefix, snake_case.');
    }

    public function testTheAccountantReplacesTheListAndItIsLogged(): void
    {
        $this->signInAs('accountant');
        $items = [
            ['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5002'],
            ['format' => '1001', 'account_prefix' => '513595', 'concept_code' => '5003'],
            ['format' => '1007', 'account_prefix' => '41', 'concept_code' => '4001'],
        ];

        $answer = $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => array_reverse($items)]);

        self::assertResponseIsSuccessful();
        self::assertSame(['items' => $items], $answer, 'The new list, sorted, replaces the old one.');
        self::assertSame(['items' => $items], $this->getJson('/api/v1/ledger/exogena-concepts'));

        $data = json_decode((string) $this->db()->fetchOne("SELECT data FROM audit_log WHERE company_id = ? AND action = 'exogena_concepts.changed'", [$this->company->toBinary()]), true);
        self::assertSame(['1001 513595 → 5003'], $data['added']);
        self::assertSame(['1001 5110: 5001 → 5002'], $data['changed']);
        self::assertSame(['1001 1435 → 5007', '1001 5120 → 5004', '1001 5135 → 5003', '1001 5195 → 5016', '1001 6205 → 5007', '1007 42 → 4002'], $data['removed']);
    }

    public function testSavingTheSameListLogsNothing(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => self::DEFAULTS]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_log WHERE company_id = ? AND action = 'exogena_concepts.changed'", [$this->company->toBinary()]));
    }

    public function testTheListCanBeEmptied(): void
    {
        self::assertSame(['items' => []], $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => []]));
    }

    public function testBadRowsAreRefusedByField(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => [
            ['format' => '1003', 'account_prefix' => '5110', 'concept_code' => '5001'],
            ['format' => '1001', 'account_prefix' => '51a', 'concept_code' => '50'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        $messages = array_column($this->body()['violations'], 'message', 'field');
        self::assertSame([
            'items[0].format' => 'Elige el formato 1001 o 1007.',
            'items[1].account_prefix' => 'Escribe el comienzo de un código de cuenta (solo dígitos).',
            'items[1].concept_code' => 'Escribe el código del concepto DIAN (cuatro dígitos).',
        ], $messages, 'One translated message per bad field.');
        self::assertSame(self::DEFAULTS, $this->getJson('/api/v1/ledger/exogena-concepts')['items'], 'Nothing changed.');
    }

    public function testThePrefixIsOncePerFormat(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => [
            ['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5001'],
            ['format' => '1007', 'account_prefix' => '5110', 'concept_code' => '4001'],
            ['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5002'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['field' => 'items[2].account_prefix', 'message' => 'Ese prefijo ya está en la lista de este formato.']], array_map(static fn (array $v) => ['field' => $v['field'], 'message' => $v['message']], $this->body()['violations']));
    }

    public function testOnlyWhoKeepsTheBooksReadsAndChangesIt(): void
    {
        $this->signInAs('billing');

        $this->client->request('GET', '/api/v1/ledger/exogena-concepts');
        self::assertResponseStatusCodeSame(403, 'The concept table belongs to the books.');
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => []]);
        self::assertResponseStatusCodeSame(403);

        $this->signOut();
        $this->client->request('GET', '/api/v1/ledger/exogena-concepts');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnotherCompanyKeepsItsOwnList(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => []]);
        $this->signOut();
        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');

        self::assertSame(self::DEFAULTS, $this->getJson('/api/v1/ledger/exogena-concepts')['items'], 'Our emptied list is not theirs.');
        $this->sendJson('PUT', '/api/v1/ledger/exogena-concepts', ['items' => [['format' => '1001', 'account_prefix' => '5110', 'concept_code' => '5002']]]);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM ledger_exogena_concept WHERE company_id <> ?', [$this->company->toBinary()]));
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM ledger_exogena_concept WHERE company_id = ?', [$this->company->toBinary()]), 'Theirs did not touch ours.');
    }
}
