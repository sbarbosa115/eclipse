<?php

namespace App\Tests\Functional\Company;

use App\Access\Domain\Model\Role;
use App\Tests\Functional\Ledger\CatalogTestCase;
use App\Tests\Support\BuildsNumbering;

final class ResolutionApiTest extends CatalogTestCase
{
    use BuildsNumbering;

    private static function day(string $relative): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->modify($relative)->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function resolution(array $override = []): array
    {
        return $override + ['resolution_number' => '18760000001', 'prefix' => 'SETP', 'range_from' => 1, 'range_to' => 1000, 'valid_from' => self::day('-10 days'), 'valid_to' => self::day('+300 days'), 'mode' => 'electronic'];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<mixed>
     */
    private function create(array $override = []): array
    {
        return $this->sendJson('POST', '/api/v1/company/resolution', $this->resolution($override));
    }

    public function testBeforeSettingItUpThereIsNoResolutionAndTheStatusSaysSo(): void
    {
        $this->signUpOwner();

        $settings = $this->getJson('/api/v1/company/resolution');

        self::assertNull($settings['resolution']);
        self::assertSame(['missing', false, 100, 30], [$settings['status']['status'], $settings['status']['warning'], $settings['status']['warning_numbers'], $settings['status']['warning_days']]);
        self::assertNull($settings['manual_invoicing_confirmed_at']);
    }

    public function testTheOwnerSetsTheResolutionUpAndSeesItActive(): void
    {
        $companyId = $this->signUpOwner();

        $created = $this->create();

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['18760000001', 'SETP', 1, 1000, 'electronic', 1, false], [$created['resolution']['resolution_number'], $created['resolution']['prefix'], $created['resolution']['range_from'], $created['resolution']['range_to'], $created['resolution']['mode'], $created['resolution']['next_number'], $created['resolution']['has_issued_numbers']]);
        self::assertSame(['active', 1000, false], [$created['status']['status'], $created['status']['numbers_left'], $created['status']['warning']]);
        self::assertContains($created['status']['days_left'], [299, 300, 301], 'About 300 days remain (the day boundary depends on the hour).');
        self::assertSame($created, $this->getJson('/api/v1/company/resolution'));
        self::assertCount(1, $this->audit($companyId, 'resolution.created'), 'The setup is in the audit log.');
        self::assertSame($created['status'], $this->getJson('/api/v1/company/resolution/status'));
    }

    public function testThereIsOnlyOneResolutionPerCompany(): void
    {
        $this->signUpOwner();
        $this->create();

        $body = $this->create(['prefix' => 'OTRA']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('resolution_exists', $body['error']);
    }

    public function testEditingBeforeThereIsOneIsA404(): void
    {
        $this->signUpOwner();

        $body = $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('resolution_not_found', $body['error']);
    }

    public function testDesdeCannotBeAfterHastaAndTheStartCannotBeAfterTheEnd(): void
    {
        $this->signUpOwner();

        $body = $this->create(['range_from' => 50, 'range_to' => 10, 'valid_from' => '2026-05-02', 'valid_to' => '2026-05-01']);

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['range_to', 'valid_to'], array_column($body['violations'], 'field'), 'Both mistakes are reported at once.');
        self::assertSame('missing', $this->getJson('/api/v1/company/resolution/status')['status'], 'Nothing was saved.');
    }

    public function testTheShapeOfEachFieldIsChecked(): void
    {
        $this->signUpOwner();
        $cases = ['resolution_number' => ['resolution_number' => ''], 'prefix' => ['prefix' => 'SE-TP'], 'range_from' => ['range_from' => 0], 'valid_from' => ['valid_from' => '01/02/2026'], 'mode' => ['mode' => 'papel']];

        foreach ($cases as $field => $override) {
            $body = $this->create($override);
            self::assertResponseStatusCodeSame(422, $field);
            self::assertSame($field, $body['violations'][0]['field']);
        }
    }

    public function testManualModeIsRefusedUntilTheOwnerConfirmsTheDianPermission(): void
    {
        $companyId = $this->signUpOwner();

        $body = $this->create(['mode' => 'manual']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('mode', $body['violations'][0]['field']);
        self::assertSame('La facturación manual necesita que el propietario confirme primero el permiso de la DIAN.', $body['violations'][0]['message']);

        $confirmed = $this->sendJson('POST', '/api/v1/company/manual-invoicing-confirmation', []);
        self::assertResponseIsSuccessful();
        self::assertNotNull($confirmed['manual_invoicing_confirmed_at']);
        $created = $this->create(['mode' => 'manual']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('manual', $created['resolution']['mode']);
        $log = $this->audit($companyId, 'company.manual_invoicing_confirmed');
        self::assertCount(1, $log, 'The confirmation is logged, with who made it.');
        self::assertNotNull($log[0]->userId());
    }

    public function testOnlyTheOwnerCanConfirmManualInvoicing(): void
    {
        $companyId = $this->signUpOwner();
        $this->signInAs(Role::Accountant, $companyId);

        $this->sendJson('POST', '/api/v1/company/manual-invoicing-confirmation', []);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->audit($companyId, 'company.manual_invoicing_confirmed'));
    }

    public function testTheModeCanGoBackToElectronicAtAnyTime(): void
    {
        $this->signUpOwner();
        $this->sendJson('POST', '/api/v1/company/manual-invoicing-confirmation', []);
        $this->create(['mode' => 'manual']);

        $edited = $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution(['mode' => 'electronic']));

        self::assertResponseIsSuccessful();
        self::assertSame('electronic', $edited['resolution']['mode']);
    }

    public function testAnUnusedResolutionCanBeEditedFreely(): void
    {
        $companyId = $this->signUpOwner();
        $this->create();

        $edited = $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution(['prefix' => 'fe', 'range_from' => 500, 'range_to' => 900]));

        self::assertResponseIsSuccessful();
        self::assertSame(['FE', 500, 900, 500], [$edited['resolution']['prefix'], $edited['resolution']['range_from'], $edited['resolution']['range_to'], $edited['resolution']['next_number']]);
        $log = $this->audit($companyId, 'resolution.updated');
        self::assertSame('SETP', $log[0]->data()['from']['prefix']);
        self::assertSame('FE', $log[0]->data()['to']['prefix']);
    }

    public function testOnceInvoicesWereNumberedDesdeAndPrefixAreLockedAndHastaHasAFloor(): void
    {
        $this->signUpOwner();
        $this->create();
        $this->numberInvoices(10);

        $locked = $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution(['prefix' => 'FE', 'range_from' => 2, 'range_to' => 5]));

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['prefix', 'range_from', 'range_to'], array_column($locked['violations'], 'field'));
        $ok = $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution(['range_to' => 10, 'resolution_number' => '18760000002']));
        self::assertResponseIsSuccessful('Hasta may stay at the last number used.');
        self::assertSame(['exhausted', 11, true], [$ok['status']['status'], $ok['resolution']['next_number'], $ok['resolution']['has_issued_numbers']]);
    }

    public function testTheWarningAppearsUnderTheNumbersThreshold(): void
    {
        $this->signUpOwner();
        $this->create(['range_to' => 150]);
        self::assertFalse($this->getJson('/api/v1/company/resolution/status')['warning'], '150 left of a default threshold of 100.');

        $this->numberInvoices(60);
        $status = $this->getJson('/api/v1/company/resolution/status');

        self::assertSame(['active', 90, true], [$status['status'], $status['numbers_left'], $status['warning']], '90 numbers left is under 100.');
    }

    public function testTheWarningAppearsUnderTheDaysThreshold(): void
    {
        $this->signUpOwner();
        $this->create(['valid_to' => self::day('+10 days')]);

        $status = $this->getJson('/api/v1/company/resolution/status');

        self::assertTrue($status['warning'], 'Ten days left is under 30.');
        self::assertContains($status['days_left'], [9, 10, 11]);
    }

    public function testTheThresholdsAreEditable(): void
    {
        $companyId = $this->signUpOwner();
        $this->create(['valid_to' => self::day('+10 days'), 'range_to' => 1000]);

        $settings = $this->sendJson('PUT', '/api/v1/company/resolution/warnings', ['warning_numbers' => 10, 'warning_days' => 3]);

        self::assertResponseIsSuccessful();
        self::assertSame([10, 3, false], [$settings['status']['warning_numbers'], $settings['status']['warning_days'], $settings['status']['warning']], 'With the new thresholds 10 days is nothing to warn about.');
        self::assertCount(1, $this->audit($companyId, 'company.resolution_warnings_updated'));
        $this->sendJson('PUT', '/api/v1/company/resolution/warnings', ['warning_numbers' => -1, 'warning_days' => 3]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testStatusesForAResolutionNotYetValidExpiredOrExhausted(): void
    {
        $this->signUpOwner();
        $this->create(['valid_from' => self::day('+5 days'), 'valid_to' => self::day('+60 days')]);
        self::assertSame(['not_yet_valid', false], [$this->getJson('/api/v1/company/resolution/status')['status'], $this->getJson('/api/v1/company/resolution/status')['warning']]);

        $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution(['valid_from' => self::day('-60 days'), 'valid_to' => self::day('-1 day')]));
        $expired = $this->getJson('/api/v1/company/resolution/status');
        self::assertSame(['expired', 0, false], [$expired['status'], $expired['days_left'], $expired['warning']]);
    }

    public function testAccountantAndBillingReadButOnlyTheOwnerWrites(): void
    {
        $companyId = $this->signUpOwner();
        $this->create();

        foreach ([Role::Accountant, Role::Billing] as $role) {
            $this->signInAs($role, $companyId);
            self::assertSame('active', $this->getJson('/api/v1/company/resolution')['status']['status'], "$role->value reads the resolution.");
            self::assertSame('active', $this->getJson('/api/v1/company/resolution/status')['status'], "$role->value reads the status (and its warning).");
            $this->sendJson('PUT', '/api/v1/company/resolution', $this->resolution());
            self::assertResponseStatusCodeSame(403, "$role->value cannot edit.");
            $this->sendJson('POST', '/api/v1/company/resolution', $this->resolution());
            self::assertResponseStatusCodeSame(403);
            $this->sendJson('PUT', '/api/v1/company/resolution/warnings', ['warning_numbers' => 1, 'warning_days' => 1]);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testACompanyNeverSeesAnotherCompanysResolution(): void
    {
        $this->signUpOwner('ana@a.co', '900123456', 'A');
        $this->create();
        $this->signOut();
        $this->signUpOwner('beto@b.co', '800197268', 'B');

        self::assertNull($this->getJson('/api/v1/company/resolution')['resolution'], 'B has none; A\'s is invisible to B.');
        $this->create(['prefix' => 'BBBB']);
        self::assertResponseStatusCodeSame(201, 'And B may set its own up.');
    }

    /** Numbers invoices the way an emission does: from inside the application, under the lock. */
    private function numberInvoices(int $count): void
    {
        $companyId = $this->em()->getConnection()->fetchOne('SELECT HEX(company_id) FROM invoicing_resolution LIMIT 1');
        $numbering = self::salesInvoiceNumbering($this->em());
        $id = \Symfony\Component\Uid\Uuid::fromString(strtolower(substr($companyId, 0, 8).'-'.substr($companyId, 8, 4).'-'.substr($companyId, 12, 4).'-'.substr($companyId, 16, 4).'-'.substr($companyId, 20)));
        $this->em()->wrapInTransaction(static function () use ($numbering, $id, $count): void {
            for ($i = 0; $i < $count; ++$i) {
                $numbering->next($id, new \DateTimeImmutable('now'));
            }
        });
        $this->em()->clear();
    }
}
