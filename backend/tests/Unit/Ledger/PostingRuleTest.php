<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Domain\Error\AccountNotAllowedForConcept;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use App\Ledger\Domain\Model\PostingRule;
use App\Shared\Domain\Accounting\PostingConcept;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PostingRuleTest extends TestCase
{
    private Uuid $company;

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
    }

    private function account(string $code, bool $active = true): Account
    {
        $account = new Account($this->company, $code, 'CUENTA '.$code, AccountNature::Credit, substr($code, 0, -2), true);
        if (!$active) {
            $account->deactivate();
        }

        return $account;
    }

    public function testRevenueMovesToAnother41Account(): void
    {
        $rule = new PostingRule($this->company, PostingConcept::Revenue, $this->account('413595')->id());
        $services = $this->account('415595');

        $rule->pointTo($services);

        self::assertTrue($services->id()->equals($rule->accountId()), '§9 Q1: the accountant picks 4155 or another 41xx.');
    }

    /** @return iterable<string, array{PostingConcept, string}> */
    public static function wrongPlaces(): iterable
    {
        yield 'revenue to an expense' => [PostingConcept::Revenue, '519595'];
        yield 'revenue to non-operating income' => [PostingConcept::Revenue, '421040'];
        yield 'receivables to a liability' => [PostingConcept::Receivables, '220505'];
        yield 'payables to an asset' => [PostingConcept::Payables, '130505'];
        yield 'iva generado outside 2408' => [PostingConcept::VatGenerated, '240405'];
        yield 'retención sufrida outside 1355' => [PostingConcept::WithholdingSuffered, '133005'];
        yield 'default expense to revenue' => [PostingConcept::DefaultExpense, '413595'];
    }

    #[DataProvider('wrongPlaces')]
    public function testAConceptPostsWithinItsPartOfThePuc(PostingConcept $concept, string $code): void
    {
        $rule = new PostingRule($this->company, $concept, Uuid::v7());

        $this->expectException(AccountNotAllowedForConcept::class);
        $rule->pointTo($this->account($code));
    }

    /** @return iterable<string, array{PostingConcept, string}> */
    public static function rightPlaces(): iterable
    {
        yield 'payables to costos y gastos por pagar (Q5)' => [PostingConcept::Payables, '233525'];
        yield 'default expense to a cost' => [PostingConcept::DefaultExpense, '613595'];
        yield 'merchandise to inventory (stage 3)' => [PostingConcept::MerchandisePurchases, '143505'];
        yield 'retefuente practicada by concept' => [PostingConcept::WithholdingPracticed, '236540'];
        yield 'sales discount to its auxiliar' => [PostingConcept::SalesDiscount, '417501'];
    }

    #[DataProvider('rightPlaces')]
    public function testAConceptAcceptsAnyPostableAccountOfItsPart(PostingConcept $concept, string $code): void
    {
        $rule = new PostingRule($this->company, $concept, Uuid::v7());
        $account = $this->account($code);

        $rule->pointTo($account);

        self::assertTrue($account->id()->equals($rule->accountId()));
    }

    public function testARuleNeverPointsToACuentaThatGroupsOthers(): void
    {
        $rule = new PostingRule($this->company, PostingConcept::Revenue, Uuid::v7());

        $this->expectException(AccountNotPostable::class);
        $rule->pointTo($this->account('4135'));
    }

    public function testARuleNeverPointsToAnInactiveAccount(): void
    {
        $rule = new PostingRule($this->company, PostingConcept::Revenue, Uuid::v7());

        $this->expectException(AccountNotPostable::class);
        $rule->pointTo($this->account('415595', active: false));
    }
}
