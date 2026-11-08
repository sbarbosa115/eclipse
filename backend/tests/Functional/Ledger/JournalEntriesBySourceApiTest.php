<?php

namespace App\Tests\Functional\Ledger;

use App\Shared\Domain\Accounting\PostingConcept as C;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Observations PRD §4.5: GET /ledger/entries?source_type=&source_id= gives a document's own entries (READ_DOCUMENTS).
 */
final class JournalEntriesBySourceApiTest extends ApiTestCase
{
    use LedgerFixtures;

    private Uuid $entry;
    private string $sourceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
        $client = $this->tercero('Cliente Uno S.A.S.');
        $this->entry = $this->post([self::line('debit', '595000.00', C::Receivables, $client), self::line('credit', '500000.00', C::Revenue), self::line('credit', '95000.00', C::VatGenerated)], date: '2026-10-05', number: 'FE-2');
        $this->post([self::line('debit', '238000.00', C::Receivables, $client), self::line('credit', '238000.00', C::Revenue)], date: '2026-10-06', number: 'FE-3');
        $this->sourceId = Uuid::fromBinary((string) $this->db()->fetchOne("SELECT source_id FROM journal_entry WHERE source_number = 'FE-2'"))->toRfc4122();
    }

    private function entries_(string $type = 'sales_invoice', ?string $id = null): array
    {
        return $this->getJson('/api/v1/ledger/entries?source_type='.$type.'&source_id='.($id ?? $this->sourceId));
    }

    public function testABillingUserSeesTheEntryOfAnEmittedDocument(): void
    {
        $this->signInAs('billing');

        $body = $this->entries_();

        self::assertResponseIsSuccessful('READ_DOCUMENTS: a document\'s own postings are part of the record.');
        self::assertCount(1, $body['items']);
        $entry = $body['items'][0];
        self::assertSame(['FE-2', null, '595000.00', '595000.00'], [$entry['source_number'], $entry['reverses_id'], $entry['total_debit'], $entry['total_credit']]);
        self::assertCount(3, $entry['lines']);
        self::assertSame('Cliente Uno S.A.S.', $entry['lines'][0]['tercero_name']);
    }

    public function testAVoidedDocumentListsItsReversalAfterTheEntryByNumber(): void
    {
        $reversal = $this->reverse($this->entry, '2026-10-25');

        $items = $this->entries_()['items'];

        self::assertCount(2, $items);
        self::assertSame([null, $this->entry->toRfc4122()], array_column($items, 'reverses_id'));
        self::assertSame($reversal->toRfc4122(), $items[1]['id']);
        self::assertLessThan($items[1]['number'], $items[0]['number'], 'By number.');
    }

    public function testAnotherCompanyGetsNoEntriesForThisDocument(): void
    {
        $this->signOut();
        $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');

        self::assertSame([], $this->entries_()['items'], 'Another company\'s document id answers an empty list, never its entry.');
    }

    public function testADocumentOfAnotherTypeWithTheSameIdHasNone(): void
    {
        self::assertSame([], $this->entries_('cash_receipt')['items']);
    }

    public function testAnUnknownSourceTypeOrABadIdIsRefused(): void
    {
        foreach ([['invoice', $this->sourceId], ['sales_invoice', 'not-a-uuid'], ['sales_invoice', ''], ['', $this->sourceId]] as [$type, $id]) {
            $this->entries_($type, $id);
            self::assertResponseStatusCodeSame(400, "$type / $id");
            self::assertSame('invalid_source', $this->body()['error']);
        }
    }

    public function testEveryRoleThatReadsDocumentsMayAskAndAnonymousMayNot(): void
    {
        $this->signInAs('accountant');
        $this->entries_();
        self::assertResponseIsSuccessful();

        $this->signOut();
        $this->entries_();
        self::assertResponseStatusCodeSame(401);
    }
}
