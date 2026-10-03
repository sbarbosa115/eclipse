<?php

namespace App\Tests\Functional\Ledger;

use App\Ledger\Application\Provisioning\CatalogProvisioner;
use App\Ledger\Application\Query\LedgerCatalog;
use Symfony\Component\Uid\Uuid;

/**
 * The seed points the taxes and payment methods at the chart's accounts when the chart (seeded just before, by the
 * "ledger" item) has them.
 */
final class CatalogProvisionerTest extends CatalogTestCase
{
    public function testTheSeedLooksUpTheAccountsOfTheChart(): void
    {
        $this->signUpOwner();
        // A company the sign-up did not make: no row of `company`, so the foreign keys are off for this test.
        $this->em()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $company = Uuid::v7();
        $ids = [];
        foreach (['240805', '240810', '135515', '236525', '236540', '236515', '135517', '11050501', '11100501'] as $code) {
            $ids[$code] = $this->account($company, $code);
        }

        static::getContainer()->get(CatalogProvisioner::class)->provision($company);
        $this->em()->flush();
        $this->em()->clear();

        $catalog = static::getContainer()->get(LedgerCatalog::class);
        $taxes = array_column($catalog->taxes($company), null, 'name');
        self::assertSame([$ids['240805'], $ids['240810']], [$taxes['IVA 19 %']->salesAccountId, $taxes['IVA 19 %']->purchaseAccountId], 'IVA: generado on sales, descontable on purchases.');
        self::assertSame([$ids['135515'], $ids['236525']], [$taxes['ReteFuente servicios 4 %']->salesAccountId, $taxes['ReteFuente servicios 4 %']->purchaseAccountId], 'Retención sufrida on sales, practicada by concept on purchases.');
        self::assertSame($ids['236540'], $taxes['ReteFuente compras 2,5 %']->purchaseAccountId);
        self::assertSame($ids['236515'], $taxes['ReteFuente honorarios 10 %']->purchaseAccountId);
        self::assertSame($ids['135517'], $taxes['ReteIVA 15 %']->salesAccountId);
        self::assertNull($taxes['ReteIVA 15 %']->purchaseAccountId, 'No 2367xx auxiliar in this chart: null, and posting falls back to the rule.');
        self::assertNull($taxes['Impoconsumo 8 %']->salesAccountId, 'No 2495xx sub-account in this chart: null.');
        $methods = array_column($catalog->paymentMethods($company), null, 'name');
        self::assertSame($ids['11050501'], $methods['Efectivo']->accountId);
        self::assertSame($ids['11100501'], $methods['Transferencia']->accountId);
        self::assertSame($ids['11100501'], $methods['Tarjeta débito']->accountId);
        self::assertNull($methods['Crédito']->accountId);
    }
}
