<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Application\PostableAccounts;
use App\Ledger\Application\Query\AccountView;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Error\AccountHasAuxiliaries;
use App\Ledger\Domain\Error\AccountNotFound;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use App\Ledger\Domain\Repository\AccountRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The one rule of where entries post (observations PRD §4.1): an active subcuenta or auxiliar with no active child. A
 * subcuenta the accountant subdivided stops receiving movements; its auxiliares take them.
 */
final class PostableAccountsTest extends TestCase
{
    private Uuid $company;
    /** @var array<string, Account> by code */
    private array $chart = [];

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
    }

    private function account(string $code, bool $active = true): Account
    {
        $parent = match (\strlen($code)) {
            1 => null,
            2 => $code[0],
            4 => substr($code, 0, 2),
            default => substr($code, 0, -2),
        };
        $account = new Account($this->company, $code, 'CUENTA '.$code, AccountNature::Credit, $parent, true);
        if (!$active) {
            $account->deactivate();
        }

        return $this->chart[$code] = $account;
    }

    private function rule(?AccountView $view = null): PostableAccounts
    {
        $chart = &$this->chart;
        $accounts = new class($chart) implements AccountRepository {
            /** @param array<string, Account> $chart */
            public function __construct(private array &$chart)
            {
            }

            public function get(Uuid $companyId, Uuid $accountId): Account
            {
                foreach ($this->chart as $account) {
                    if ($account->id()->equals($accountId) && $account->companyId()->equals($companyId)) {
                        return $account;
                    }
                }

                throw new AccountNotFound();
            }

            public function byCode(Uuid $companyId, string $code): ?Account
            {
                return $this->chart[$code] ?? null;
            }

            public function hasActiveChildren(Uuid $companyId, string $code): bool
            {
                foreach ($this->chart as $account) {
                    if ($account->parentCode() === $code && $account->isActive()) {
                        return true;
                    }
                }

                return false;
            }

            public function add(Account $account): void
            {
                $this->chart[$account->code()] = $account;
            }
        };
        $catalog = $this->createStub(LedgerCatalog::class);
        if (null !== $view) {
            $catalog->method('account')->willReturn($view);
        }

        return new PostableAccounts($catalog, $accounts);
    }

    public function testASubcuentaWithoutAuxiliaresTakesMovements(): void
    {
        $revenue = $this->account('413595');

        self::assertTrue($this->rule()->isPostable($revenue));
        $this->rule()->assertPostable($revenue);
    }

    public function testASubcuentaWithAnActiveAuxiliarTakesNone(): void
    {
        $revenue = $this->account('413595');
        $this->account('41359501');

        self::assertFalse($this->rule()->isPostable($revenue), 'Colombian practice: the movements go to one of its auxiliares.');
        try {
            $this->rule()->assertPostable($revenue);
            self::fail('A subdivided subcuenta is refused.');
        } catch (AccountHasAuxiliaries $e) {
            self::assertSame('account_has_auxiliaries', $e->errorCode());
            self::assertSame(['account_code' => '413595'], $e->details(), 'The message names the account.');
        }
    }

    public function testAnAuxiliarTakesMovementsUnderItsSubdividedParent(): void
    {
        $this->account('413595');
        $auxiliar = $this->account('41359501');

        self::assertTrue($this->rule()->isPostable($auxiliar));
    }

    public function testOnlyInactiveAuxiliaresLeaveTheParentPostable(): void
    {
        $revenue = $this->account('413595');
        $this->account('41359501', active: false);

        self::assertTrue($this->rule()->isPostable($revenue), 'A deactivated auxiliar hands the movements back to its parent.');
    }

    public function testAnAuxiliarWithItsOwnActiveAuxiliarTakesNone(): void
    {
        $this->account('413595');
        $auxiliar = $this->account('41359501');
        $this->account('4135950101');

        self::assertFalse($this->rule()->isPostable($auxiliar), 'The rule holds at every depth.');
    }

    public function testACuentaOrAnInactiveSubcuentaIsNotPostableForTheOldReason(): void
    {
        $this->expectException(AccountNotPostable::class);

        $this->rule()->assertPostable($this->account('4135'));
    }

    public function testAnInactiveSubcuentaIsNotPostable(): void
    {
        $this->expectException(AccountNotPostable::class);

        $this->rule()->assertPostable($this->account('413595', active: false));
    }

    public function testATaxFormNamingASubdividedAccountIsToldWhy(): void
    {
        $id = Uuid::v7();
        $subdivided = new AccountView($id->toRfc4122(), '240805', 'IVA GENERADO', 'credit', 'subaccount', '2408', true, true, false, false);

        try {
            $this->rule($subdivided)->forTax($this->company, $id->toRfc4122(), 'sales_account_id');
            self::fail('A subdivided account is refused on the form too.');
        } catch (InvalidTaxDefinition $e) {
            self::assertStringContainsString('auxiliar', $e->getMessage(), 'The violation says to pick one of its auxiliares.');
        }
    }
}
