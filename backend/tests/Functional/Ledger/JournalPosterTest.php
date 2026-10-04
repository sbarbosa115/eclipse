<?php

namespace App\Tests\Functional\Ledger;

use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Ledger\Domain\Error\EntryAlreadyReversed;
use App\Ledger\Domain\Error\EntryEmpty;
use App\Ledger\Domain\Error\EntryUnbalanced;
use App\Ledger\Domain\Error\JournalEntryNotFound;
use App\Ledger\Domain\Error\PeriodLocked;
use App\Ledger\Domain\Error\PostingRuleMissing;
use App\Ledger\Domain\Model\JournalEntry;
use App\Ledger\Domain\Model\JournalLine;
use App\Shared\Domain\Accounting\PostingConcept as C;
use App\Tests\Support\ApiTestCase;

/**
 * The one way into the books (JournalPoster), against PRD Appendix A: concepts resolve through the posting rules (or
 * the tercero's own account for clientes/proveedores), entries balance to the cent, nothing lands on or before the
 * fecha de bloqueo, and a void is the mirror entry.
 */
final class JournalPosterTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /**
     * @return list<array{string, string, string, ?string}> code, débito, crédito, tercero
     */
    private static function movements(JournalEntry $entry): array
    {
        return array_map(static fn (JournalLine $l) => [$l->accountCode(), $l->debit()->toString(), $l->credit()->toString(), $l->terceroId()?->toRfc4122()], $entry->lines());
    }

    public function testASalesInvoiceIsPostedAsAppendixA1(): void
    {
        // Total bruto 1.000.000, 10 % de descuento, IVA 19 % on 900.000, ReteFuente 2,5 % suffered; half paid in cash.
        $client = $this->tercero();
        $id = $this->post([
            self::line('debit', '500000.00', $this->account('11050501')),
            self::line('debit', '548500.00', C::Receivables, $client),
            self::line('debit', '100000.00', C::SalesDiscount),
            self::line('debit', '22500.00', C::WithholdingSuffered, $client),
            self::line('credit', '1000000.00', C::Revenue),
            self::line('credit', '171000.00', C::VatGenerated),
        ], date: '2026-10-01', number: 'FE-1');

        [$entry] = $this->entries();
        self::assertTrue($id->equals($entry->id()));
        self::assertSame([
            ['11050501', '500000.00', '0.00', null],
            ['13050501', '548500.00', '0.00', $client->toRfc4122()],
            ['417501', '100000.00', '0.00', null],
            ['135515', '22500.00', '0.00', $client->toRfc4122()],
            ['413595', '0.00', '1000000.00', null],
            ['240805', '0.00', '171000.00', null],
        ], self::movements($entry), 'A.1: caja, 1305 (tercero), 4175, 1355 débito; 41xx and 2408 crédito.');
        self::assertSame('1171000.00', $entry->totalDebit()->toString(), 'Σ débitos = total neto + retenciones + descuentos.');
        self::assertSame(1, $entry->number(), 'The first entry of the company.');
        self::assertSame('2026-10-01', $entry->entryDate()->format('Y-m-d'));
        self::assertSame(['sales_invoice', 'FE-1', 'Documento FE-1'], [$entry->sourceType(), $entry->sourceNumber(), $entry->description()]);
        self::assertTrue($this->owner->equals($entry->createdBy()));
        self::assertNull($entry->reversesId());
    }

    public function testAPurchaseOfAServiceIsPostedAsAppendixA3(): void
    {
        // AC-5: a service with IVA 19 % and ReteFuente 4 %, on credit.
        $supplier = $this->tercero('Proveedor Uno');
        $this->post([
            self::line('debit', '1000000.00', $this->account('513595')),
            self::line('debit', '190000.00', C::VatDeductible),
            self::line('credit', '40000.00', C::WithholdingPracticed, $supplier),
            self::line('credit', '1150000.00', C::Payables, $supplier),
        ], type: 'purchase_invoice', number: 'FC-1');

        [$entry] = $this->entries();
        self::assertSame([
            ['513595', '1000000.00', '0.00', null],
            ['240810', '190000.00', '0.00', null],
            ['236570', '0.00', '40000.00', $supplier->toRfc4122()],
            ['22050501', '0.00', '1150000.00', $supplier->toRfc4122()],
        ], self::movements($entry));
    }

    public function testEntriesAreNumberedOnePerCompany(): void
    {
        $this->post([self::line('debit', '10.00', $this->account('11050501')), self::line('credit', '10.00', C::Revenue)]);
        $this->post([self::line('debit', '20.00', $this->account('11050501')), self::line('credit', '20.00', C::Revenue)], number: 'FE-2');
        $mine = $this->company;
        $this->signOut();
        $other = $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');
        $this->post([self::line('debit', '5.00', $this->account('11050501')), self::line('credit', '5.00', C::Revenue)], company: $other);

        self::assertSame([1, 2], array_map(static fn (JournalEntry $e) => $e->number(), $this->entries($mine)));
        self::assertSame([1], array_map(static fn (JournalEntry $e) => $e->number(), $this->entries($other)), 'Each company has its own consecutive.');
    }

    public function testATerceroWithItsOwnAccountsOverridesClientesAndProveedoresOnly(): void
    {
        $abroad = $this->tercero('Client Abroad Inc.', receivable: '13051001', payable: '233525');
        $plain = $this->tercero('Cliente Local');
        $this->post([
            self::line('debit', '100.00', C::Receivables, $abroad),
            self::line('debit', '50.00', C::Receivables, $plain),
            self::line('credit', '100.00', C::Payables, $abroad),
            self::line('credit', '50.00', C::Revenue, $abroad),
        ]);

        self::assertSame(['13051001', '13050501', '233525', '413595'], array_column(self::movements($this->entries()[0]), 0), '§4.2: the tercero\'s override for 1305/2205, the posting rule otherwise.');
    }

    public function testAnUnbalancedEntryIsRefusedBeforeItTakesANumber(): void
    {
        try {
            $this->post([self::line('debit', '100.00', $this->account('11050501')), self::line('credit', '99.99', C::Revenue)]);
            self::fail('§5 invariant 1.');
        } catch (EntryUnbalanced $e) {
            self::assertSame('entry_unbalanced', $e->errorCode());
            self::assertSame(['debit' => '100.00', 'credit' => '99.99'], $e->details());
        }
        $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)]);

        self::assertSame([1], array_map(static fn (JournalEntry $e) => $e->number(), $this->entries()), 'Nothing was stored, and no number was spent.');
    }

    public function testNothingIsPostedOnOrBeforeTheLockDate(): void
    {
        $this->lockBooks('2026-09-30');
        $lines = [self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)];

        self::assertFalse($this->poster()->isOpen($this->company, new \DateTimeImmutable('2026-09-30')));
        self::assertTrue($this->poster()->isOpen($this->company, new \DateTimeImmutable('2026-10-01')));
        try {
            $this->post($lines, date: '2026-09-30');
            self::fail('§5 invariant 4: on or before the fecha de bloqueo.');
        } catch (PeriodLocked $e) {
            self::assertSame('period_locked', $e->errorCode());
            self::assertSame(['locked_until' => '2026-09-30'], $e->details());
        }
        $this->post($lines, date: '2026-10-01');
        self::assertCount(1, $this->entries());
    }

    public function testAConceptWithoutARuleIsRefused(): void
    {
        $this->db()->delete('posting_rule', ['company_id' => $this->company->toBinary(), 'concept' => 'ingreso']);

        $this->expectException(PostingRuleMissing::class);
        $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)]);
    }

    public function testACuentaThatGroupsOthersTakesNoMovement(): void
    {
        $this->expectException(AccountNotPostable::class);
        $this->post([self::line('debit', '1.00', $this->account('1105')), self::line('credit', '1.00', C::Revenue)]);
    }

    public function testAnInactiveAccountTakesNoMovement(): void
    {
        $this->db()->update('ledger_account', ['active' => 0], ['id' => $this->account('11100501')->toBinary()]);

        $this->expectException(AccountNotPostable::class);
        $this->post([self::line('debit', '1.00', $this->account('11100501')), self::line('credit', '1.00', C::Revenue)]);
    }

    public function testAnotherCompanysAccountIsNotFound(): void
    {
        $mine = $this->company;
        $this->signOut();
        $theirs = $this->account('11050501', $this->startCompany('beto@b.co', '800197268', 'B S.A.S.'));

        $this->expectException(AccountNotFound::class);
        $this->post([self::line('debit', '1.00', $theirs), self::line('credit', '1.00', C::Revenue)], company: $mine);
    }

    public function testZeroMovementsAreSkipped(): void
    {
        $this->post([
            self::line('debit', '1190.00', $this->account('11050501')),
            self::line('debit', '0.00', C::SalesDiscount),
            self::line('credit', '1000.00', C::Revenue),
            self::line('credit', '190.00', C::VatGenerated),
            self::line('credit', '0.00', C::ConsumptionTax),
        ]);

        self::assertSame(['11050501', '413595', '240805'], array_column(self::movements($this->entries()[0]), 0));
    }

    public function testAnEntryOfOnlyZerosIsRefused(): void
    {
        $this->expectException(EntryEmpty::class);
        $this->post([self::line('debit', '0.00', $this->account('11050501')), self::line('credit', '0.00', C::Revenue)]);
    }

    public function testAChangedPostingRuleAppliesToTheNextEntry(): void
    {
        $this->sendJson('PUT', '/api/v1/posting-rules/ingreso', ['account_id' => $this->account('415595')->toRfc4122()]);
        self::assertResponseIsSuccessful();

        $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)]);

        self::assertSame('415595', $this->entries()[0]->lines()[1]->accountCode(), '§9 Q1: services post to 4155.');
    }

    public function testAVoidPostsTheMirrorEntryDatedTheVoidDate(): void
    {
        $client = $this->tercero();
        $original = $this->post([
            self::line('debit', '1190.00', C::Receivables, $client),
            self::line('credit', '1000.00', C::Revenue),
            self::line('credit', '190.00', C::VatGenerated),
        ], date: '2026-10-01');

        $reversal = $this->inTransaction(fn () => $this->poster()->reverse($this->company, $original, new \DateTimeImmutable('2026-10-02'), $this->owner, 'Anulación FE-1'));

        [$first, $second] = $this->entries();
        self::assertTrue($reversal->equals($second->id()));
        self::assertTrue($original->equals($second->reversesId()), 'The reversal points at what it reverses.');
        self::assertSame('2026-10-02', $second->entryDate()->format('Y-m-d'), '§4.12: dated the void date.');
        self::assertSame(2, $second->number());
        self::assertSame(['sales_invoice', 'FE-1', 'Anulación FE-1'], [$second->sourceType(), $second->sourceNumber(), $second->description()]);
        self::assertSame([
            ['13050501', '0.00', '1190.00', $client->toRfc4122()],
            ['413595', '1000.00', '0.00', null],
            ['240805', '190.00', '0.00', null],
        ], self::movements($second), 'Débitos and créditos swapped, same accounts and terceros.');
        self::assertSame('1190.00', $first->totalDebit()->toString(), 'The original is never touched.');
        self::assertSame('13050501', $first->lines()[0]->accountCode());
    }

    public function testAnEntryIsReversedOnce(): void
    {
        $original = $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)]);
        $this->reverse($original, '2026-10-02');

        $this->expectException(EntryAlreadyReversed::class);
        $this->reverse($original, '2026-10-03');
    }

    public function testAVoidIsNotDatedOnOrBeforeTheLockDate(): void
    {
        $original = $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)], date: '2026-09-15');
        $this->lockBooks('2026-09-30');

        $this->expectException(PeriodLocked::class);
        $this->reverse($original, '2026-09-30');
    }

    public function testAVoidOfAnInactiveAccountsEntryStillPosts(): void
    {
        $original = $this->post([self::line('debit', '1.00', $this->account('11100501')), self::line('credit', '1.00', C::Revenue)]);
        $this->db()->update('ledger_account', ['active' => 0], ['id' => $this->account('11100501')->toBinary()]);

        $this->reverse($original, '2026-10-02');

        self::assertCount(2, $this->entries(), 'A void undoes what was posted, whatever happened to the account since.');
    }

    public function testAnotherCompanysEntryCannotBeReversed(): void
    {
        $theirs = $this->post([self::line('debit', '1.00', $this->account('11050501')), self::line('credit', '1.00', C::Revenue)]);
        $this->signOut();
        $mine = $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');

        $this->expectException(JournalEntryNotFound::class);
        $this->reverse($theirs, '2026-10-02', $mine);
    }
}
