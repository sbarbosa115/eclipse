<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\Access\InvitesUsers;
use App\Tests\Functional\Sales\QuotationFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * Security audit 2026-10-04, finding 1: anyone may sign up, and a signed-in user could make the app e-mail any address
 * (a tercero's) as often as they liked: a spam relay under the app's sender. A company now sends a bounded number of
 * e-mails an hour (config/packages/rate_limiter.yaml; the test environment allows 10 of each): 429 `too_many_emails`
 * after that, and nothing is sent or emitted.
 */
final class EmailQuotaTest extends ApiTestCase
{
    use InvitesUsers;
    use QuotationFixtures;

    private const TEST_LIMIT = 10;

    public function testADocumentIsSentAtMostTheCompanysHourlyQuota(): void
    {
        $this->startCompany();
        $quotation = $this->emittedQuotation();

        for ($i = 0; $i < self::TEST_LIMIT; ++$i) {
            $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/send', []);
            self::assertResponseStatusCodeSame(202);
            self::assertEmailCount(1);
        }
        $body = $this->sendJson('POST', '/api/v1/quotations/'.$quotation['id'].'/send', []);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('too_many_emails', $body['error']);
        self::assertEmailCount(0, 'Nothing is sent once the quota is spent.');

        $draft = $this->quotationDraft($this->quotationPayload($this->client(number: '890903938'), $this->service('B')));
        $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit-and-send', []);
        self::assertResponseStatusCodeSame(429, 'Emit and send is refused whole: the quotation stays a draft.');
        self::assertSame('draft', $this->getJson('/api/v1/quotations/'.$draft['id'])['status']);
    }

    public function testInvitationsAreBoundedToo(): void
    {
        $this->signUp();
        for ($i = 0; $i < self::TEST_LIMIT; ++$i) {
            $this->invite("persona$i@acme.co");
        }
        $body = $this->sendJson('POST', '/api/v1/users/invitations', ['email' => 'otra@acme.co', 'role' => 'billing']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('too_many_emails', $body['error']);
    }

    public function testEachCompanyHasItsOwnQuota(): void
    {
        $this->signUp();
        for ($i = 0; $i < self::TEST_LIMIT; ++$i) {
            $this->invite("persona$i@acme.co");
        }
        $this->signOut();
        $this->signUp('eva@beta.co', '900765432', 'Beta S.A.S.');

        $this->invite('alguien@beta.co');
    }
}
