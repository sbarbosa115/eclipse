<?php

namespace App\Tests\Functional\Ledger;

use App\Company\Domain\Model\Company;
use App\Ledger\Application\Seed\ProvisionLedger;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §4.1: a new company receives the PUC (Decreto 2650, Art. 6: classes, groups, cuentas and subcuentas) plus Mustang's
 * auxiliares (§5, A.10), the default posting rules and open books, inside the sign-up.
 */
final class PucSeedTest extends ApiTestCase
{
    use LedgerFixtures;

    /** @return array<string, array<string, mixed>> by code */
    private function chart(?Uuid $company = null): array
    {
        $rows = $this->db()->fetchAllAssociative('SELECT code, name, nature, level, parent_code, standard, active, usable_on_purchases FROM ledger_account WHERE company_id = ?', [($company ?? $this->company)->toBinary()]);

        return array_column($rows, null, 'code');
    }

    public function testANewCompanyGetsTheWholePucDownToSubcuentas(): void
    {
        $this->startCompany();
        $chart = $this->chart();
        $levels = array_count_values(array_map(static fn (array $a) => $a['level'], array_filter($chart, static fn (array $a) => (bool) $a['standard'])));

        self::assertSame(9, $levels['class'], 'Classes 1 to 9.');
        self::assertGreaterThanOrEqual(50, $levels['group']);
        self::assertGreaterThanOrEqual(330, $levels['account'], 'Every cuenta of the catálogo.');
        self::assertGreaterThanOrEqual(2100, $levels['subaccount'], 'Every subcuenta of the catálogo.');
        self::assertArrayNotHasKey('auxiliary', $levels, 'The PUC stops at 6 digits; auxiliares are the company\'s.');
        foreach ($chart as $code => $account) {
            if (1 === \strlen((string) $code)) {
                self::assertNull($account['parent_code']);
                continue;
            }
            self::assertArrayHasKey((string) $account['parent_code'], $chart, "$code hangs from an account of the chart.");
            self::assertStringStartsWith((string) $account['parent_code'], (string) $code);
        }
    }

    public function testEveryAccountTheSpecificationNamesIsThere(): void
    {
        $this->startCompany();
        $chart = $this->chart();

        // Appendix A.10, and the accounts §5 and Appendix A post to.
        $named = ['110505', '111005', '130505', '130510', '135515', '135517', '135518', '2205', '2335', '236515', '236520',
            '236525', '236530', '236540', '236570', '2365', '2367', '2368', '2408', '2495', '4135', '4155', '4175', '5105', '5110',
            '5115', '5120', '5125', '5130', '5135', '5140', '5145', '5150', '5155', '5160', '5165', '5195', '6205', '1435',
            '6135', '6225', '2210', '2380', '1355', '1305'];
        foreach ($named as $code) {
            self::assertArrayHasKey($code, $chart, "The PUC seed has $code.");
            self::assertTrue((bool) $chart[$code]['standard'], "$code is the PUC's.");
        }
        self::assertSame('IMPUESTO A LAS VENTAS RETENIDO', $chart['2367']['name']);
        self::assertSame('DEVOLUCIONES, REBAJAS Y DESCUENTOS EN VENTAS (DB)', $chart['4175']['name'], 'Name per D.R. 2894/94.');
        self::assertArrayNotHasKey('3110', $chart, 'Accounts the decree eliminated are left out.');
    }

    public function testAccountsRunOnTheSideTheirClassAndTheDecreeSay(): void
    {
        $this->startCompany();
        $chart = $this->chart();

        self::assertSame('debit', $chart['4175']['nature'], '4175 is (DB) inside the crédito class 4.');
        self::assertSame('debit', $chart['417501']['nature'], 'Its auxiliar runs the same way.');
        self::assertSame('credit', $chart['6225']['nature'], '6225 is (CR) inside the débito class 6.');
        self::assertSame('credit', $chart['1592']['nature'], 'Depreciación acumulada reduces an asset.');
        self::assertSame('credit', $chart['159205']['nature']);
        self::assertSame('debit', $chart['110505']['nature']);
        self::assertSame('credit', $chart['2408']['nature']);
        self::assertSame('credit', $chart['3105']['nature']);
        self::assertSame('debit', $chart['310510']['nature'], 'Capital por suscribir (DB).');
    }

    public function testMustangsAuxiliaresHangFromTheirOfficialParents(): void
    {
        $this->startCompany();
        $chart = $this->chart();

        $expected = ['11050501' => '110505', '11100501' => '111005', '13050501' => '130505', '13051001' => '130510',
            '22050501' => '220505', '220505' => '2205', '240805' => '2408', '240810' => '2408', '236701' => '2367',
            '236801' => '2368', '249505' => '2495', '417501' => '4175', '620501' => '6205'];
        foreach ($expected as $code => $parent) {
            self::assertArrayHasKey($code, $chart, "Mustang creates $code.");
            self::assertSame($parent, $chart[$code]['parent_code'], "$code is under $parent.");
            self::assertFalse((bool) $chart[$code]['standard'], "$code is the company's own (Art. 6), so it can be renamed.");
            self::assertTrue((bool) $chart[$code]['active']);
        }
        self::assertSame('auxiliary', $chart['11050501']['level']);
        self::assertSame('subaccount', $chart['240805']['level']);
        self::assertSame('IVA GENERADO', $chart['240805']['name']);
        self::assertSame('IVA DESCONTABLE', $chart['240810']['name']);
    }

    public function testOnlyClasses5To7AreUsableOnPurchasesByDefault(): void
    {
        $this->startCompany();

        foreach ($this->chart() as $code => $account) {
            self::assertSame(\in_array(((string) $code)[0], ['5', '6', '7'], true), (bool) $account['usable_on_purchases'], "§9 Q14 for $code.");
        }
    }

    public function testEveryConceptGetsItsDefaultAccount(): void
    {
        $this->startCompany();
        $rules = $this->db()->fetchAllKeyValue('SELECT r.concept, a.code FROM posting_rule r JOIN ledger_account a ON a.id = r.account_id WHERE r.company_id = ?', [$this->company->toBinary()]);

        self::assertEqualsCanonicalizing(array_map(static fn (PostingConcept $c) => $c->value, PostingConcept::cases()), array_keys($rules), 'One rule per concept.');
        self::assertEquals([
            'ingreso' => '413595',
            'descuento_ventas' => '417501',
            'iva_generado' => '240805',
            'iva_descontable' => '240810',
            'impoconsumo' => '249505',
            'retefuente_sufrida' => '135515',
            'reteiva_sufrida' => '135517',
            'reteica_sufrida' => '135518',
            'clientes' => '13050501',
            'proveedores' => '22050501',
            'retefuente_practicada' => '236570',
            'reteiva_practicada' => '236701',
            'reteica_practicada' => '236801',
            'gasto_por_defecto' => '519595',
            'compra_mercancias' => '620501',
        ], $rules, 'The defaults of §5.');
    }

    public function testTheBooksStartOpen(): void
    {
        $this->startCompany();

        self::assertSame([null], $this->db()->fetchFirstColumn('SELECT locked_until FROM ledger_settings WHERE company_id = ?', [$this->company->toBinary()]));
    }

    public function testEachCompanyGetsItsOwnChart(): void
    {
        $first = $this->startCompany();
        $this->signOut();
        $second = $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');

        self::assertCount(\count($this->chart($first)), $this->chart($second));
        self::assertFalse($this->account('110505', $first)->equals($this->account('110505', $second)), 'Accounts are rows of their company.');
    }

    public function testSeedingACompanyIsFast(): void
    {
        $this->startCompany();
        $company = new Company('Rápida S.A.S.', IdentificationType::Nit, '811000111', '0', new \DateTimeImmutable());
        $this->save($company);
        $provisioner = static::getContainer()->get(ProvisionLedger::class);

        $started = hrtime(true);
        $provisioner->provision($company->id());
        $this->em()->flush();
        $milliseconds = (hrtime(true) - $started) / 1e6;

        self::assertGreaterThan(2500, \count($this->chart($company->id())));
        // Thousands of rows on every sign-up: batch inserts keep it well under a second (measured ~100 ms locally).
        self::assertLessThan(1000, $milliseconds, \sprintf('Seeding took %.0f ms.', $milliseconds));
        fwrite(\STDERR, \sprintf("\n[PucSeedTest] seeding one company: %.0f ms\n", $milliseconds));
    }
}
