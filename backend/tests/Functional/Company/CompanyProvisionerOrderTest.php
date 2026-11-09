<?php

namespace App\Tests\Functional\Company;

use App\Company\Application\CompanyRegistration;
use App\Company\Application\ProvisionNumberingSeries;
use App\Ledger\Application\Exogena\ProvisionExogenaConcepts;
use App\Ledger\Application\Provisioning\CatalogProvisioner;
use App\Ledger\Application\Seed\ProvisionLedger;
use App\Shared\Application\Company\CompanyProvisioner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

/**
 * Sign-up runs every context's provisioner once, in the order services.yaml gives their tags: the numbering series,
 * the chart, the taxes and payment methods that point at its accounts, then the exógena concepts that point at both.
 */
final class CompanyProvisionerOrderTest extends KernelTestCase
{
    public function testSignUpRunsEveryProvisionerOnceInPriorityOrder(): void
    {
        self::bootKernel();
        $registration = self::getContainer()->get(CompanyRegistration::class);
        $provisioners = (new \ReflectionProperty(CompanyRegistration::class, 'provisioners'))->getValue($registration);

        $classes = [];
        foreach ($provisioners as $provisioner) {
            $classes[] = $provisioner::class;
        }

        self::assertSame([
            ProvisionNumberingSeries::class,
            ProvisionLedger::class,
            CatalogProvisioner::class,
            ProvisionExogenaConcepts::class,
        ], $classes);
    }

    public function testEveryImplementationIsWired(): void
    {
        $found = [];
        foreach ((new Finder())->files()->in(\dirname(__DIR__, 3).'/src')->name('*.php')->contains('implements CompanyProvisioner') as $file) {
            $found[] = $file->getFilenameWithoutExtension();
        }
        sort($found);

        self::assertSame(['CatalogProvisioner', 'ProvisionExogenaConcepts', 'ProvisionLedger', 'ProvisionNumberingSeries'], $found, 'A new '.CompanyProvisioner::class.' needs its tag and priority in services.yaml, and a place in the test above.');
    }
}
