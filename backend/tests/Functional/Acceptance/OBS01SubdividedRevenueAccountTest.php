<?php

namespace App\Tests\Functional\Acceptance;

use App\Access\Domain\Model\Role;
use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Posting\Side;
use App\Ledger\Domain\Error\AccountHasAuxiliaries;
use App\Shared\Domain\Money\Money;
use App\Tests\Functional\Sales\SalesInvoiceFixtures;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * OBS-1 (observations PRD §8): the accountant adds 41359501 and 41359502 under 413595 (the ingreso rule): the answer
 * lists what moved, the rule now points at 41359501, the product form's picker finds 41359502 by code and by
 * "gravados"; an emitted invoice posts revenue to 41359501, never to 413595; posting explicitly to 413595 is refused.
 */
final class OBS01SubdividedRevenueAccountTest extends ApiTestCase
{
    use SalesInvoiceFixtures;

    public function testRevenueGoesToTheFirstAuxiliarOnceTheAccountIsSubdivided(): void
    {
        $this->startCompany();
        $this->signInAs(Role::Accountant);

        $first = $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => '413595', 'code' => '41359501', 'name' => 'Ventas gravados']);
        self::assertResponseStatusCodeSame(201);
        $second = $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => '413595', 'code' => '41359502', 'name' => 'Productos gravados celulares']);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(['ingreso'], $first['moved']['posting_rules'] ?? null, 'The answer lists what moved.');
        self::assertNull($second['moved'], 'The second auxiliar finds nothing left on the parent.');
        $rules = array_column($this->getJson('/api/v1/posting-rules')['items'], 'account_code', 'concept');
        self::assertSame('41359501', $rules['ingreso']);

        foreach (['41359502', 'gravados', '4135'] as $query) {
            self::assertContains('41359502', array_column($this->getJson('/api/v1/accounts/search?q='.$query)['items'], 'code'), "The picker finds it by \"$query\".");
        }

        $this->signOut();
        $this->signIn('ana@acme.co');
        $client = $this->client();
        $draft = $this->draft($this->payload($client, $this->service('ASESORIA'), ['payments' => [$this->cash('1190000.00')]]));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful(json_encode($invoice) ?: '');

        $codes = array_column(self::movements($this->entries()[0]), 0);
        self::assertContains('41359501', $codes, 'Revenue posts to the auxiliar…');
        self::assertNotContains('413595', $codes, '…never to its parent.');

        $this->expectException(AccountHasAuxiliaries::class);
        $poster = static::getContainer()->get(JournalPoster::class);
        $poster->post(new EntryDraft($this->company, new \DateTimeImmutable(), 'sales_invoice', Uuid::v7(), 'X-1', 'Explícito', Uuid::v7(), [
            EntryLine::toAccount(Side::Debit, Money::of('1.00'), Uuid::fromString($this->accountId('11050501'))),
            EntryLine::toAccount(Side::Credit, Money::of('1.00'), Uuid::fromString($this->accountId('413595'))),
        ]));
    }
}
