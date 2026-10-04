<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * AC-8: emission beyond the resolution's hasta or dates is impossible; the owner was warned beforehand.
 */
final class AC08ResolutionLimitsTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    public function testNothingIsEmittedPastHastaAndTheOwnerWasWarned(): void
    {
        $this->startCompany(resolution: false);
        $this->resolution(['range_from' => 1, 'range_to' => 2]);
        $client = $this->client();

        $status = $this->getJson('/api/v1/company/resolution/status');
        self::assertTrue($status['warning'], 'Warned beforehand: fewer numbers left than the threshold.');
        self::assertSame(2, $status['numbers_left']);

        $this->emitted(client: $client);
        $this->emitted(client: $client);
        self::assertSame('exhausted', $this->getJson('/api/v1/company/resolution/status')['status']);

        $draft = $this->draft($this->payload($client, $this->service('ULTIMO')));
        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('resolution_exhausted', $body['error'], 'Beyond hasta: impossible.');
        self::assertSame('draft', $this->getJson('/api/v1/sales-invoices/'.$draft['id'])['status']);
        self::assertCount(2, $this->entries());
    }

    public function testNothingIsEmittedOutsideTheResolutionsDates(): void
    {
        $this->startCompany(resolution: false);
        $this->resolution(['valid_from' => self::today('-10 days'), 'valid_to' => self::today('+5 days')]);
        self::assertTrue($this->getJson('/api/v1/company/resolution/status')['warning'], 'Warned: fewer days left than the threshold.');

        $draft = $this->draft($this->payload($this->client(), $this->service(), ['issue_date' => self::today('-20 days')]));
        $body = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('resolution_inactive', $body['error'], 'An invoice dated before the resolution\'s dates is impossible.');
        self::assertSame([], $this->entries());
    }
}
