<?php

namespace App\Tests\Functional\Ledger;

use App\Shared\Domain\Accounting\PostingConcept;
use App\Tests\Support\ApiTestCase;

/**
 * §5: the posting rules, seeded with the defaults and edited by the accountant or the owner, every change logged.
 */
final class PostingRuleApiTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /** @return array<mixed> */
    private function change(string $concept, string $accountId): array
    {
        return $this->sendJson('PUT', "/api/v1/posting-rules/$concept", ['account_id' => $accountId]);
    }

    public function testEveryConceptIsListedWithItsAccount(): void
    {
        $items = $this->getJson('/api/v1/posting-rules')['items'];

        self::assertResponseIsSuccessful();
        self::assertSame(array_map(static fn (PostingConcept $c) => $c->value, PostingConcept::cases()), array_column($items, 'concept'), 'In the order of §5.');
        self::assertSame([
            'concept' => 'ingreso',
            'account_id' => $this->account('413595')->toRfc4122(),
            'account_code' => '413595',
            'account_name' => 'VENTA DE OTROS PRODUCTOS',
            'allowed_prefixes' => ['41'],
        ], $items[0]);
    }

    public function testTheAccountantMovesRevenueToServicesAndItIsLogged(): void
    {
        $this->signInAs('accountant');

        $rule = $this->change('ingreso', $this->account('415595')->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSame(['ingreso', '415595'], [$rule['concept'], $rule['account_code']]);
        $log = $this->db()->fetchAssociative("SELECT action, subject_type, data, user_id FROM audit_log WHERE company_id = ? AND action = 'posting_rule.changed'", [$this->company->toBinary()]);
        self::assertIsArray($log, 'Editing a rule is logged in audit_log.');
        self::assertSame('posting_rule', $log['subject_type']);
        self::assertSame(['concept' => 'ingreso', 'from' => '413595', 'to' => '415595'], json_decode($log['data'], true));
        self::assertNotNull($log['user_id']);
    }

    public function testTheOwnerMayChangeARuleToo(): void
    {
        $this->change('proveedores', $this->account('233525')->toRfc4122());

        self::assertResponseIsSuccessful('§9 Q5: services to 2335 Costos y gastos por pagar.');
    }

    public function testARuleStaysWithinItsPartOfThePuc(): void
    {
        $this->change('ingreso', $this->account('519595')->toRfc4122());
        self::assertResponseStatusCodeSame(422);
        self::assertSame('account_not_allowed_for_concept', $this->body()['error']);

        $this->change('ingreso', $this->account('4135')->toRfc4122());
        self::assertResponseStatusCodeSame(409);
        self::assertSame('account_not_postable', $this->body()['error']);

        $this->change('no_such_concept', $this->account('415595')->toRfc4122());
        self::assertResponseStatusCodeSame(404);

        $this->sendJson('PUT', '/api/v1/posting-rules/ingreso', []);
        self::assertResponseStatusCodeSame(422);

        self::assertSame('413595', $this->getJson('/api/v1/posting-rules')['items'][0]['account_code'], 'Nothing changed.');
    }

    public function testAnotherCompanysAccountIsNotFound(): void
    {
        $this->signOut();
        $theirs = $this->account('415595', $this->startCompany('beto@b.co', '800197268', 'B S.A.S.'));
        $this->signOut();
        $this->signIn('ana@acme.co');

        $this->change('ingreso', $theirs->toRfc4122());

        self::assertResponseStatusCodeSame(404);
    }

    public function testABillingUserCannotChangeRules(): void
    {
        $this->signInAs('billing');

        $this->change('ingreso', $this->account('415595')->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }
}
