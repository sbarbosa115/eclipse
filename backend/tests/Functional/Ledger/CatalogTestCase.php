<?php

namespace App\Tests\Functional\Ledger;

use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\AccountNature;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationLine;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Model\SalesInvoicePayment;
use App\Shared\Domain\Model\AuditLog;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the taxes and payment methods API tests share: a company signed up through the API, accounts of its chart,
 * people of the other roles, and the audit trail.
 */
abstract class CatalogTestCase extends ApiTestCase
{
    use SignsUp;

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function signUpOwner(string $email = 'ana@acme.co', string $nit = '900123456', string $company = 'Acme S.A.S.'): Uuid
    {
        return Uuid::fromString($this->signUp($email, $nit, $company)['company_id']);
    }

    /** An account of the company's chart (the "ledger" item seeds the real one). */
    protected function account(Uuid $companyId, string $code, string $name = 'Cuenta de prueba', AccountNature $nature = AccountNature::Debit): string
    {
        $account = new Account($companyId, $code, $name, $nature, null, true);
        $this->save($account);

        return $account->id()->toRfc4122();
    }

    /** Signs the owner out and a person of another role in, in the same company. */
    protected function signInAs(Role $role, Uuid $companyId): void
    {
        $this->signOut();
        $user = User::invite($companyId, $role->value.'@acme.co', ucfirst($role->value), $role, new \DateTimeImmutable());
        $this->save($user);
        $this->setUserActive($user->id());
        $this->signIn($role->value.'@acme.co');
    }

    private function setUserActive(Uuid $id): void
    {
        $user = $this->em()->find(User::class, $id);
        \assert($user instanceof User);
        foreach (['passwordHash' => password_hash('correct horse battery', \PASSWORD_BCRYPT, ['cost' => 4]), 'status' => UserStatus::Active] as $property => $value) {
            (new \ReflectionProperty(User::class, $property))->setValue($user, $value);
        }
        $this->em()->flush();
        $this->em()->clear();
    }

    /**
     * @return list<AuditLog> the company's trail for one action, oldest first
     */
    protected function audit(Uuid $companyId, string $action): array
    {
        /** @var list<AuditLog> $rows */
        $rows = $this->em()->getRepository(AuditLog::class)->findBy(['companyId' => $companyId, 'action' => $action], ['occurredAt' => 'ASC']);

        return $rows;
    }

    /** A cotización with one line that copied this tax (the terceros item is not built: its foreign key is switched off). */
    protected function useTaxOnAQuotation(Uuid $companyId, string $taxId): void
    {
        $this->em()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $quotation = new Quotation($companyId, Uuid::v7(), 'Cliente', new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31'), Uuid::v7(), new \DateTimeImmutable());
        $line = new QuotationLine($quotation, $companyId, 1, null, 'Servicio', Quantity::of(1), UnitPrice::of(100), Rate::zero(), new TaxSnapshot(Uuid::fromString($taxId), 'IVA', 'iva', TaxCalculation::Percentage, '19.0000', null), TaxSnapshot::none());
        $this->save($quotation, $line);
        $this->em()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** A factura de venta whose forma de pago was this method. */
    protected function usePaymentMethodOnAnInvoice(Uuid $companyId, string $methodId, PaymentKind $kind = PaymentKind::Cash): void
    {
        $this->em()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $invoice = new SalesInvoice($companyId, Uuid::v7(), 'Cliente', new \DateTimeImmutable('2026-10-01'), Uuid::v7(), new \DateTimeImmutable());
        $payment = new SalesInvoicePayment($invoice, $companyId, 1, Uuid::fromString($methodId), 'Efectivo', $kind, null, Money::of(100), null);
        $this->save($invoice, $payment);
        $this->em()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
