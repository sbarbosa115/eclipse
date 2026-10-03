<?php

namespace App\Tests\Functional\Ledger;

use App\Tests\Support\ApiTestCase;

/**
 * §4.1 Fecha de bloqueo contable: the accountant (or the owner) moves it; it is logged; nothing posts on or before it.
 */
final class LockDateApiTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    public function testTheBooksStartOpen(): void
    {
        self::assertSame(['locked_until' => null], $this->getJson('/api/v1/ledger/lock-date'));
    }

    public function testTheAccountantLocksAPeriodAndItIsLogged(): void
    {
        $this->signInAs('accountant');

        $answer = $this->sendJson('PUT', '/api/v1/ledger/lock-date', ['locked_until' => '2026-09-30']);

        self::assertResponseIsSuccessful();
        self::assertSame(['locked_until' => '2026-09-30'], $answer);
        self::assertSame(['locked_until' => '2026-09-30'], $this->getJson('/api/v1/ledger/lock-date'));
        self::assertFalse($this->poster()->isOpen($this->company, new \DateTimeImmutable('2026-09-30')));
        $data = $this->db()->fetchOne("SELECT data FROM audit_log WHERE company_id = ? AND action = 'ledger.lock_date_moved'", [$this->company->toBinary()]);
        self::assertSame(['from' => null, 'to' => '2026-09-30'], json_decode((string) $data, true));
    }

    public function testTheBooksCannotBeLockedBeyondToday(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/lock-date', ['locked_until' => '2999-01-01']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('lock_date_in_future', $this->body()['error']);
    }

    public function testADateIsADate(): void
    {
        $this->sendJson('PUT', '/api/v1/ledger/lock-date', ['locked_until' => '30/09/2026']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('locked_until', $this->body()['violations'][0]['field']);
    }

    public function testABillingUserSeesButCannotMoveIt(): void
    {
        $this->signInAs('billing');

        $this->getJson('/api/v1/ledger/lock-date');
        self::assertResponseIsSuccessful('Document forms check dates against it.');
        $this->sendJson('PUT', '/api/v1/ledger/lock-date', ['locked_until' => '2026-09-30']);
        self::assertResponseStatusCodeSame(403);
    }
}
