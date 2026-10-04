<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Domain\Error\AccountCodeInvalid;
use App\Ledger\Domain\Error\StandardAccountName;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountLevel;
use App\Ledger\Domain\Model\AccountNature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AccountTest extends TestCase
{
    private Uuid $company;

    protected function setUp(): void
    {
        $this->company = Uuid::v7();
    }

    private function puc(string $code, string $name, AccountNature $nature = AccountNature::Credit): Account
    {
        return new Account($this->company, $code, $name, $nature, null, true);
    }

    public function testACompanySubaccountExtendsItsCuentaByTwoDigits(): void
    {
        $iva = Account::under($this->puc('2408', 'IMPUESTO SOBRE LAS VENTAS POR PAGAR'), '240805', 'IVA GENERADO');

        self::assertSame(AccountLevel::Subaccount, $iva->level());
        self::assertSame('2408', $iva->parentCode());
        self::assertSame(AccountNature::Credit, $iva->nature(), 'A new account runs on its parent\'s side.');
        self::assertFalse($iva->isStandard(), 'What the company adds is its own, not the PUC\'s.');
        self::assertTrue($iva->isPostable());
        self::assertSame($this->company, $iva->companyId());
    }

    public function testAnAuxiliarGoesUnderASubcuenta(): void
    {
        $caja = Account::under($this->puc('110505', 'CAJA GENERAL', AccountNature::Debit), '11050501', 'CAJA PRINCIPAL');

        self::assertSame(AccountLevel::Auxiliary, $caja->level());
        self::assertSame(AccountNature::Debit, $caja->nature());
    }

    /** @return iterable<string, array{string, string}> */
    public static function badCodes(): iterable
    {
        yield 'another parent' => ['2408', '240905'];
        yield 'one digit more' => ['2408', '24080'];
        yield 'four digits more' => ['2408', '24080501'];
        yield 'letters' => ['2408', '2408AB'];
        yield 'under a group' => ['24', '2499'];
        yield 'under a class' => ['2', '29'];
    }

    #[DataProvider('badCodes')]
    public function testANewCodeMustExtendACuentaOrDeeperByTwoDigits(string $parent, string $code): void
    {
        $this->expectException(AccountCodeInvalid::class);

        Account::under($this->puc($parent, 'PADRE'), $code, 'NUEVA');
    }

    public function testAccountsOfClasses5To7AreUsableOnPurchasesByDefault(): void
    {
        $expense = Account::under($this->puc('5135', 'SERVICIOS', AccountNature::Debit), '513596', 'ASEO EXTERNO');
        $liability = Account::under($this->puc('2335', 'COSTOS Y GASTOS POR PAGAR'), '233596', 'OTROS');

        self::assertTrue($expense->isUsableOnPurchases(), '§9 Q14: purchase lines post to classes 5, 6 and 7.');
        self::assertFalse($liability->isUsableOnPurchases());
        self::assertTrue(Account::under($this->puc('2335', 'X'), '233597', 'Y', usableOnPurchases: true)->isUsableOnPurchases(), 'The accountant may allow any other account.');
    }

    public function testAPucAccountKeepsItsOfficialName(): void
    {
        $this->expectException(StandardAccountName::class);

        $this->puc('1105', 'CAJA')->rename('EFECTIVO');
    }

    public function testACompanyAccountIsRenamedAndDeactivated(): void
    {
        $account = Account::under($this->puc('110505', 'CAJA GENERAL', AccountNature::Debit), '11050502', 'CAJA 2');

        $account->rename('  Caja sucursal norte  ');
        $account->deactivate();

        self::assertSame('Caja sucursal norte', $account->name());
        self::assertFalse($account->isPostable(), 'Nothing posts to an inactive account.');
        $account->activate();
        self::assertTrue($account->isPostable());
    }

    public function testRenamingAPucAccountToItsOwnNameIsNoChange(): void
    {
        $caja = $this->puc('1105', 'CAJA');

        $caja->rename('CAJA');

        self::assertSame('CAJA', $caja->name());
    }
}
