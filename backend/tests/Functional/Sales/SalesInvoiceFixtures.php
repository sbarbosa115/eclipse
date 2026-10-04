<?php

namespace App\Tests\Functional\Sales;

use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Model\JournalEntry;
use App\Ledger\Domain\Model\JournalLine;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the sales invoice tests share: a company signed up through the API with its invoicing resolution, a client and
 * services made through their own APIs, the seeded taxes and payment methods, and a way to read the books.
 *
 * @mixin ApiTestCase
 */
trait SalesInvoiceFixtures
{
    use SignsUp;

    protected Uuid $company;
    private ?string $defaultClient = null;

    /** Today in Colombia, as the API dates documents. */
    protected static function today(string $modify = 'today'): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->modify($modify)->format('Y-m-d');
    }

    protected function startCompany(string $email = 'ana@acme.co', string $nit = '900123456', string $name = 'Acme S.A.S.', bool $resolution = true): Uuid
    {
        $this->company = Uuid::fromString($this->signUp($email, $nit, $name)['company_id']);
        if ($resolution) {
            $this->resolution();
        }

        return $this->company;
    }

    /**
     * @param array<string, mixed> $over
     */
    protected function resolution(array $over = []): void
    {
        $this->sendJson('POST', '/api/v1/company/resolution', $over + [
            'resolution_number' => '18764000001234',
            'prefix' => 'FE',
            'range_from' => 1,
            'range_to' => 1000,
            'valid_from' => self::today('-1 year'),
            'valid_to' => self::today('+1 year'),
            'mode' => 'electronic',
        ]);
        self::assertResponseStatusCodeSame(201, 'The owner records the invoicing resolution.');
    }

    protected function client(string $name = 'Cliente Uno S.A.S.', ?string $email = 'facturas@cliente.co', string $number = '800197268'): string
    {
        $tercero = $this->sendJson('POST', '/api/v1/terceros/quick', [
            'person_type' => 'empresa',
            'identification_type' => 'nit',
            'identification_number' => $number,
            'business_name' => $name,
            'email' => $email ?? 'sin-correo@cliente.co',
            'roles' => ['cliente'],
        ]);
        self::assertResponseStatusCodeSame(201, 'A client is created inline (AC-3).');
        if (null === $email) {
            // Quick-create asks for an e-mail; the full form does not.
            $this->em()->getConnection()->update('tercero', ['email' => null], ['id' => Uuid::fromString($tercero['id'])->toBinary()]);
        }

        return $tercero['id'];
    }

    /**
     * @param array<string, mixed> $over
     */
    protected function service(string $code = 'CONS', string $price = '1000000', array $over = []): string
    {
        $product = $this->sendJson('POST', '/api/v1/products/quick', $over + [
            'type' => 'servicio',
            'code' => $code,
            'name' => 'Consultoría',
            'sale_price' => $price,
            'price_includes_tax' => false,
            'charge_tax_id' => $this->taxId('IVA 19 %'),
            'withholding_tax_id' => null,
        ]);
        self::assertResponseStatusCodeSame(201, 'A service is created inline (AC-3).');

        return $product['id'];
    }

    protected function taxId(string $name): string
    {
        foreach ($this->getJson('/api/v1/taxes?all=1')['items'] as $tax) {
            if ($tax['name'] === $name) {
                return $tax['id'];
            }
        }
        self::fail("No seeded tax $name.");
    }

    protected function methodId(string $name): string
    {
        foreach ($this->getJson('/api/v1/payment-methods?all=1')['items'] as $method) {
            if ($method['name'] === $name) {
                return $method['id'];
            }
        }
        self::fail("No seeded payment method $name.");
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    protected function line(string $productId, array $over = []): array
    {
        return $over + [
            'product_id' => $productId,
            'description' => 'Consultoría',
            'quantity' => '1',
            'unit_price' => '1000000',
            'discount' => '',
            'charge_tax_id' => $this->taxId('IVA 19 %'),
            'withholding_tax_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cash(string $amount, string $method = 'Efectivo'): array
    {
        return ['payment_method_id' => $this->methodId($method), 'amount' => $amount, 'due_date' => null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function credit(string $amount, ?string $due = null): array
    {
        return ['payment_method_id' => $this->methodId('Crédito'), 'amount' => $amount, 'due_date' => $due ?? self::today('+30 days')];
    }

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    protected function payload(string $client, string $product, array $over = []): array
    {
        return $over + [
            'tercero_id' => $client,
            'contact_id' => null,
            'seller_id' => null,
            'issue_date' => self::today(),
            'notes' => null,
            'lines' => [$this->line($product)],
            'payments' => [$this->cash('1190000.00')],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed> the SalesInvoiceOutput
     */
    protected function draft(array $payload): array
    {
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices', $payload);
        self::assertResponseStatusCodeSame(201, 'The draft is saved: '.json_encode($invoice));

        return $invoice;
    }

    /**
     * A client, a service of 1.000.000 + IVA 19 % and an emitted invoice paid as given (cash by default).
     *
     * @param list<array<string, mixed>>|null $payments
     *
     * @return array<string, mixed>
     */
    protected function emitted(?array $payments = null, ?string $client = null): array
    {
        $client ??= $this->defaultClient ??= $this->client();
        $draft = $this->draft($this->payload($client, $this->service('S'.random_int(1000, 9999)), ['payments' => $payments ?? [$this->cash('1190000.00')]]));
        $invoice = $this->sendJson('POST', '/api/v1/sales-invoices/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('Emitted: '.json_encode($invoice));

        return $invoice;
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function accountId(string $code): string
    {
        return (static::getContainer()->get(LedgerCatalog::class)->accountIdByCode($this->company, $code) ?? throw new \LogicException("No $code."))->toRfc4122();
    }

    /** @return list<JournalEntry> */
    protected function entries(): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $this->company], ['number' => 'ASC']);
    }

    /**
     * @return list<array{string, string, string}> code, débito, crédito
     */
    protected static function movements(JournalEntry $entry): array
    {
        return array_map(static fn (JournalLine $l) => [$l->accountCode(), $l->debit()->toString(), $l->credit()->toString()], $entry->lines());
    }

    protected function lockBooks(string $until): void
    {
        $this->em()->getConnection()->update('ledger_settings', ['locked_until' => $until], ['company_id' => $this->company->toBinary()]);
    }

    /** Signs the owner out and a person of another role in, in the same company. */
    protected function signInAs(Role $role): void
    {
        $this->signOut();
        $user = User::invite($this->company, $role->value.'@acme.co', ucfirst($role->value), $role, new \DateTimeImmutable());
        $this->em()->persist($user);
        $this->em()->flush();
        foreach (['passwordHash' => password_hash('correct horse battery', \PASSWORD_BCRYPT, ['cost' => 4]), 'status' => UserStatus::Active] as $property => $value) {
            (new \ReflectionProperty(User::class, $property))->setValue($user, $value);
        }
        $this->em()->flush();
        $this->em()->clear();
        $this->signIn($role->value.'@acme.co');
    }
}
