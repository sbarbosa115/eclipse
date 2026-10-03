<?php

namespace App\Tests\Functional\Company;

use App\Access\Domain\Model\Role;
use App\Shared\Domain\Model\Attachment;
use App\Tests\Functional\Ledger\CatalogTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class CompanyApiTest extends CatalogTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    private const JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function profile(array $override = []): array
    {
        return $override + [
            'legal_name' => 'Acme Ltda.',
            'trade_name' => 'Acme',
            'identification_type' => 'nit',
            'identification_number' => '900.123.456',
            'check_digit' => null,
            'address' => 'Calle 1 # 2-3',
            'city' => 'Bogotá',
            'phone' => '6011234567',
            'email' => 'contacto@acme.co',
            'vat_regime' => 'responsable',
            'fiscal_responsibilities' => ['O-13', 'O-15'],
            'default_charge_tax_id' => null,
            'default_withholding_tax_id' => null,
        ];
    }

    private function taxId(string $name, bool $all = true): string
    {
        $items = $this->getJson('/api/v1/taxes'.($all ? '?all=1' : ''))['items'];

        return array_column($items, 'id', 'name')[$name];
    }

    private function upload(string $bytes, string $name = 'logo.png', string $field = 'file'): void
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $bytes);
        $this->client->request('POST', '/api/v1/company/logo', files: [$field => new UploadedFile($path, $name, null, null, true)], server: ['HTTP_ACCEPT' => 'application/json']);
    }

    public function testASignedUpCompanyHasItsIdentityAndSaneDefaults(): void
    {
        $this->signUpOwner();

        $company = $this->getJson('/api/v1/company');

        self::assertSame(['Acme S.A.S.', 'nit', '900123456'], [$company['legal_name'], $company['identification_type'], $company['identification_number']]);
        self::assertNotNull($company['check_digit'], 'The DV was computed at sign-up.');
        self::assertSame(['responsable', ['R-99-PN'], null], [$company['vat_regime'], $company['fiscal_responsibilities'], $company['logo_id']]);
    }

    public function testTheOwnerEditsEveryFieldOfTheProfile(): void
    {
        $companyId = $this->signUpOwner();
        $charge = $this->taxId('IVA 5 %');
        $withholding = $this->taxId('ReteFuente servicios 4 %');

        $saved = $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_charge_tax_id' => $charge, 'default_withholding_tax_id' => $withholding]));

        self::assertResponseIsSuccessful();
        self::assertSame(['Acme Ltda.', 'Acme', '900123456', 'Calle 1 # 2-3', 'Bogotá', '6011234567', 'contacto@acme.co', $charge, $withholding, ['O-13', 'O-15']], [$saved['legal_name'], $saved['trade_name'], $saved['identification_number'], $saved['address'], $saved['city'], $saved['phone'], $saved['email'], $saved['default_charge_tax_id'], $saved['default_withholding_tax_id'], $saved['fiscal_responsibilities']], 'Dots in the NIT are dropped; everything else is stored as typed.');
        self::assertSame($saved, $this->getJson('/api/v1/company'), 'What PUT answers is what GET reads back.');
        self::assertSame('Acme Ltda.', $this->getJson('/api/v1/me')['company_name'], 'The session header follows the razón social.');
        $rows = $this->audit($companyId, 'company.updated');
        self::assertCount(1, $rows, 'Every change is written to the audit log.');
        self::assertSame('Acme S.A.S.', $rows[0]->data()['from']['legalName']);
        self::assertSame('Acme Ltda.', $rows[0]->data()['to']['legalName']);
    }

    public function testTheCheckDigitIsComputedWhenEmptyAndKeptWhenGiven(): void
    {
        $this->signUpOwner();

        $computed = $this->sendJson('PUT', '/api/v1/company', $this->profile(['identification_number' => '900123456', 'check_digit' => null]));
        $given = $this->sendJson('PUT', '/api/v1/company', $this->profile(['identification_number' => '900123456', 'check_digit' => '5']));

        self::assertMatchesRegularExpression('/^\d$/', $computed['check_digit'], 'The DIAN algorithm gives one digit.');
        self::assertSame('5', $given['check_digit'], 'The DV is editable: a given one is kept.');
    }

    public function testAnotherDocumentTypeHasNoCheckDigit(): void
    {
        $this->signUpOwner();

        $saved = $this->sendJson('PUT', '/api/v1/company', $this->profile(['identification_type' => 'cc', 'identification_number' => '1020304050', 'check_digit' => '7']));

        self::assertSame(['cc', '1020304050', null], [$saved['identification_type'], $saved['identification_number'], $saved['check_digit']]);
    }

    public function testTheNitCannotBeAnotherCompanysAndThatIsNotLeaked(): void
    {
        $this->signUpOwner('ana@a.co', '900123456', 'A');
        $this->signOut();
        $this->signUpOwner('beto@b.co', '800197268', 'B');

        $body = $this->sendJson('PUT', '/api/v1/company', $this->profile(['identification_number' => '900123456']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('identification_number', $body['violations'][0]['field']);
        self::assertSame('Ya hay una empresa registrada con este NIT.', $body['violations'][0]['message']);
    }

    public function testTheOwnNitMayBeSavedAgain(): void
    {
        $this->signUpOwner();

        $this->sendJson('PUT', '/api/v1/company', $this->profile(['identification_number' => '900123456']));

        self::assertResponseIsSuccessful('Saving the form without touching the NIT is not a clash with itself.');
    }

    public function testTheProfileIsValidatedFieldByField(): void
    {
        $this->signUpOwner();
        $cases = [
            'legal_name' => ['legal_name' => ''],
            'identification_type' => ['identification_type' => 'dni'],
            'check_digit' => ['check_digit' => '12'],
            'email' => ['email' => 'not-an-email'],
            'vat_regime' => ['vat_regime' => 'mixto'],
            'fiscal_responsibilities[0]' => ['fiscal_responsibilities' => ['X-1']],
            'default_charge_tax_id' => ['default_charge_tax_id' => 'nope'],
        ];

        foreach ($cases as $field => $override) {
            $body = $this->sendJson('PUT', '/api/v1/company', $this->profile($override));
            self::assertResponseStatusCodeSame(422, $field);
            self::assertSame($field, $body['violations'][0]['field']);
        }
    }

    public function testADefaultTaxMustBeActiveAndOfItsClass(): void
    {
        $this->signUpOwner();
        $retefuente = $this->taxId('ReteFuente servicios 4 %');
        $iva = $this->taxId('IVA 19 %');
        $ica = $this->taxId('IVA 0 %');
        $this->sendJson('POST', "/api/v1/taxes/$ica/deactivate", []);

        $wrongClass = $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_charge_tax_id' => $retefuente]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('default_charge_tax_id', $wrongClass['violations'][0]['field'], 'A retención is not an impuesto cargo.');

        $other = $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_withholding_tax_id' => $iva]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('default_withholding_tax_id', $other['violations'][0]['field']);

        $inactive = $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_charge_tax_id' => $ica]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('default_charge_tax_id', $inactive['violations'][0]['field'], 'A deactivated tax cannot become the default.');

        $unknown = $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_charge_tax_id' => Uuid::v7()->toRfc4122()]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('default_charge_tax_id', $unknown['violations'][0]['field']);
    }

    public function testAnotherCompanysTaxCannotBeTheDefault(): void
    {
        $this->signUpOwner('ana@a.co', '900123456', 'A');
        $theirs = $this->taxId('IVA 5 %');
        $this->signOut();
        $this->signUpOwner('beto@b.co', '800197268', 'B');

        $this->sendJson('PUT', '/api/v1/company', $this->profile(['default_charge_tax_id' => $theirs]));

        self::assertResponseStatusCodeSame(422, 'Another company\'s tax looks like one that does not exist.');
    }

    public function testAccountantAndBillingReadTheProfileButCannotChangeIt(): void
    {
        $companyId = $this->signUpOwner();

        foreach ([Role::Accountant, Role::Billing] as $role) {
            $this->signInAs($role, $companyId);
            self::assertSame('Acme S.A.S.', $this->getJson('/api/v1/company')['legal_name'], "$role->value reads.");
            $this->sendJson('PUT', '/api/v1/company', $this->profile());
            self::assertResponseStatusCodeSame(403, "$role->value cannot write.");
            $this->upload(base64_decode(self::PNG));
            self::assertResponseStatusCodeSame(403, "$role->value cannot upload a logo.");
        }
        self::assertSame([], $this->audit($companyId, 'company.updated'), 'A refused edit leaves no trail.');
    }

    public function testSignedOutPeopleGetNothing(): void
    {
        $this->getJson('/api/v1/company');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAPngLogoIsStoredServedAndLogged(): void
    {
        $companyId = $this->signUpOwner();

        $this->upload(base64_decode(self::PNG));
        $company = (array) json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertNotNull($company['logo_id']);
        $this->client->request('GET', '/api/v1/company/logo');
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(base64_decode(self::PNG), file_get_contents($response->getFile()->getPathname()), 'The bytes come back untouched.');
        $attachment = $this->em()->find(Attachment::class, Uuid::fromString($company['logo_id']));
        self::assertInstanceOf(Attachment::class, $attachment, 'The logo is a Shared Attachment of the company.');
        self::assertSame(['image/png', $companyId->toRfc4122()], [$attachment->contentType(), $attachment->ownerId()->toRfc4122()]);
        self::assertCount(1, $this->audit($companyId, 'company.logo_changed'));
    }

    public function testAJpegLogoReplacesThePngAndTheOldFileIsDeleted(): void
    {
        $this->signUpOwner();
        $this->upload(base64_decode(self::PNG));
        $first = Uuid::fromString($this->getJson('/api/v1/company')['logo_id']);
        $firstFile = $this->storedPath($first);
        self::assertFileExists($firstFile);

        $this->upload(base64_decode(self::JPEG), 'logo.jpg');

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        self::assertFileDoesNotExist($firstFile, 'The replaced logo does not linger on disk.');
        self::assertNull($this->em()->find(Attachment::class, $first), 'Nor in the attachments table.');
        $this->client->request('GET', '/api/v1/company/logo');
        self::assertSame('image/jpeg', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testTheLogoIsJudgedByItsContentNotItsName(): void
    {
        $this->signUpOwner();

        $this->upload('<?php echo "hi";', 'logo.png');
        self::assertResponseStatusCodeSame(415, 'A script named .png is not a PNG.');
        self::assertSame('logo_unsupported', $this->body()['error']);

        $this->upload('<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg');
        self::assertResponseStatusCodeSame(415, 'SVG can carry scripts: only PNG and JPEG.');

        $this->upload(base64_decode(self::PNG), 'logo.exe');
        self::assertResponseIsSuccessful('A real PNG with a strange name is fine: the content decides.');
    }

    public function testAMissingFileIsRejected(): void
    {
        $this->signUpOwner();

        $this->upload(base64_decode(self::PNG), 'logo.png', 'other');

        self::assertResponseStatusCodeSame(415);
    }

    public function testALogoOverTwoMegabytesIsRefused(): void
    {
        $companyId = $this->signUpOwner();
        $big = base64_decode(self::PNG).str_repeat("\0", 2 * 1024 * 1024);

        $this->upload($big);

        self::assertResponseStatusCodeSame(413);
        self::assertSame('logo_too_large', $this->body()['error']);
        self::assertNull($this->getJson('/api/v1/company')['logo_id']);
        self::assertSame([], $this->audit($companyId, 'company.logo_changed'));
    }

    public function testALogoExactlyTwoMegabytesIsAccepted(): void
    {
        $this->signUpOwner();
        $png = base64_decode(self::PNG);

        $this->upload($png.str_repeat("\0", 2 * 1024 * 1024 - \strlen($png)));

        self::assertResponseIsSuccessful('2 MB is the limit, inclusive.');
    }

    public function testTheLogoCanBeRemoved(): void
    {
        $companyId = $this->signUpOwner();
        $this->upload(base64_decode(self::PNG));
        $logoId = Uuid::fromString($this->getJson('/api/v1/company')['logo_id']);
        $file = $this->storedPath($logoId);

        $this->client->request('DELETE', '/api/v1/company/logo');

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->getJson('/api/v1/company')['logo_id']);
        self::assertFileDoesNotExist($file);
        $this->client->request('GET', '/api/v1/company/logo');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/api/v1/company/logo');
        self::assertResponseStatusCodeSame(404, 'There is nothing left to remove.');
        self::assertCount(1, $this->audit($companyId, 'company.logo_removed'));
    }

    public function testACompanyNeverSeesAnotherCompanysLogo(): void
    {
        $this->signUpOwner('ana@a.co', '900123456', 'A');
        $this->upload(base64_decode(self::PNG));
        $this->signOut();
        $this->signUpOwner('beto@b.co', '800197268', 'B');

        $this->client->request('GET', '/api/v1/company/logo');

        self::assertResponseStatusCodeSame(404, 'B has no logo; A\'s is not reachable from B.');
        self::assertSame('B', $this->getJson('/api/v1/company')['legal_name']);
    }

    private function storedPath(Uuid $logoId): string
    {
        $attachment = $this->em()->find(Attachment::class, $logoId);
        \assert($attachment instanceof Attachment);

        return $this->uploadsDir().'/'.$attachment->storageKey();
    }

    private function uploadsDir(): string
    {
        $dir = (string) ($_SERVER['UPLOADS_DIR'] ?? $_ENV['UPLOADS_DIR'] ?? '');

        return rtrim(str_replace('%kernel.project_dir%', (string) static::getContainer()->getParameter('kernel.project_dir'), $dir), '/');
    }

    /** @var list<string> */
    private array $uploadFoldersBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadFoldersBefore = glob($this->uploadsDir().'/*', \GLOB_ONLYDIR) ?: [];
    }

    /** Files are not rolled back with the database: the folders this test created go. */
    protected function tearDown(): void
    {
        foreach (array_diff(glob($this->uploadsDir().'/*', \GLOB_ONLYDIR) ?: [], $this->uploadFoldersBefore) as $folder) {
            array_map('unlink', glob($folder.'/*') ?: []);
            rmdir($folder);
        }
        parent::tearDown();
    }
}
