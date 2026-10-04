<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Domain\Model\Payable;
use App\Purchasing\Infrastructure\Persistence\DoctrinePayableLocks;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two payments for the same payable must not both pass the balance check: the payment's handler locks the
 * payables it allocates to (SELECT … FOR UPDATE) and reads their balances afresh, inside its transaction.
 *
 * The tests' own connection lives in one transaction that is never committed (DAMA), so a second request cannot be
 * simulated on it. These tests open two real connections of their own, like two PHP processes, commit their rows
 * and remove them afterwards.
 */
final class PayableLocksTest extends KernelTestCase
{
    private Connection $first;
    private Connection $second;
    private Uuid $company;
    private Uuid $payable;

    protected function setUp(): void
    {
        self::bootKernel();
        $params = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams();
        $this->first = DriverManager::getConnection($params);
        $this->second = DriverManager::getConnection($params);
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->company = Uuid::v7();
        $this->payable = Uuid::v7();
        // A payable of a company, supplier and invoice that exist only for this test: the foreign keys are not its
        // concern.
        $this->first->executeStatement('SET SESSION foreign_key_checks = 0');
        $this->first->insert('payable', [
            'id' => $this->payable->toBinary(), 'company_id' => $this->company->toBinary(), 'invoice_id' => Uuid::v7()->toBinary(),
            'invoice_number' => 'FC-1', 'tercero_id' => Uuid::v7()->toBinary(), 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'amount' => '1000.00', 'balance' => '1000.00', 'voided' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
        $this->first->delete('purchase_invoice', ['company_id' => $this->company->toBinary()]);
        $this->first->delete('payable', ['company_id' => $this->company->toBinary()]);
        $this->first->close();
        $this->second->close();
        parent::tearDown();
    }

    private function locksOn(Connection $connection): DoctrinePayableLocks
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        return new DoctrinePayableLocks(new EntityManager($connection, $em->getConfiguration()));
    }

    public function testASecondPaymentWaitsForTheFirst(): void
    {
        $this->first->beginTransaction();
        $locked = $this->locksOn($this->first)->lockForPayment($this->company, [$this->payable]);
        self::assertArrayHasKey($this->payable->toRfc4122(), $locked);

        $this->second->beginTransaction();
        try {
            $this->locksOn($this->second)->lockForPayment($this->company, [$this->payable]);
            self::fail('The second payment read the balance while the first still held the payable.');
        } catch (LockWaitTimeoutException) {
            // Waited, as a second request would until the first commits.
        }
        $this->second->rollBack();

        $this->first->rollBack();
        $this->second->beginTransaction();
        self::assertCount(1, $this->locksOn($this->second)->lockForPayment($this->company, [$this->payable]), 'Once the first one ends, the second goes on.');
    }

    public function testThePayablesInvoiceIsLockedToo(): void
    {
        $invoice = Uuid::v7();
        $this->first->update('payable', ['invoice_id' => $invoice->toBinary()], ['id' => $this->payable->toBinary()]);
        $this->insertInvoice($invoice);

        $this->first->beginTransaction();
        $this->locksOn($this->first)->lockForPayment($this->company, [$this->payable]);

        // What a void of the invoice (PurchaseInvoiceRepository::lock) or another payment of one of its payables does.
        $this->second->beginTransaction();
        try {
            $this->second->fetchOne('SELECT status FROM purchase_invoice WHERE id = ? FOR UPDATE', [$invoice->toBinary()]);
            self::fail('The invoice was free while a payment held its payable: two payments could overwrite its paid amount.');
        } catch (LockWaitTimeoutException) {
            // Waited until the payment ends.
        }
        $this->second->rollBack();

        $this->first->rollBack();
        $this->second->beginTransaction();
        self::assertSame('emitted', $this->second->fetchOne('SELECT status FROM purchase_invoice WHERE id = ? FOR UPDATE', [$invoice->toBinary()]));
    }

    public function testWithoutTheLockASecondReaderWouldNotWait(): void
    {
        // The control: a plain read takes no lock, so the test above fails for the right reason if the lock goes.
        $this->first->beginTransaction();
        $this->first->fetchOne('SELECT balance FROM payable WHERE id = ?', [$this->payable->toBinary()]);

        $this->second->beginTransaction();
        self::assertCount(1, $this->locksOn($this->second)->lockForPayment($this->company, [$this->payable]));
    }

    public function testTheSecondPaymentSeesTheBalanceTheFirstLeft(): void
    {
        $secondLocks = $this->locksOn($this->second);
        $this->second->beginTransaction();
        // The second request had already read the payable (e.g. to show it) before the first one committed.
        $secondEm = (new \ReflectionProperty(DoctrinePayableLocks::class, 'em'))->getValue($secondLocks);
        \assert($secondEm instanceof EntityManagerInterface);
        $stale = $secondEm->find(Payable::class, $this->payable);
        \assert($stale instanceof Payable);
        self::assertSame('1000.00', $stale->balance()->toString());
        $this->second->rollBack();

        $this->first->beginTransaction();
        $firstEm = new EntityManager($this->first, self::getContainer()->get(EntityManagerInterface::class)->getConfiguration());
        $mine = (new DoctrinePayableLocks($firstEm))->lockForPayment($this->company, [$this->payable])[$this->payable->toRfc4122()];
        $mine->allocate(Money::of('800.00'));
        $firstEm->flush();
        $this->first->commit();

        $this->second->beginTransaction();
        $fresh = $secondLocks->lockForPayment($this->company, [$this->payable])[$this->payable->toRfc4122()];
        self::assertSame('200.00', $fresh->balance()->toString(), 'Locked and read again: 800 are gone, so a second allocation of 800 is refused.');
    }

    public function testAnotherCompanysPayableIsLeftOut(): void
    {
        $this->first->beginTransaction();

        self::assertSame([], $this->locksOn($this->first)->lockForPayment(Uuid::v7(), [$this->payable]), 'Unknown to another company: the payment refuses it by field.');
    }

    /** A purchase invoice that exists only for this test: every required column given a value of its type. */
    private function insertInvoice(Uuid $id): void
    {
        $row = ['id' => $id->toBinary(), 'company_id' => $this->company->toBinary(), 'status' => 'emitted'];
        $columns = $this->first->fetchAllAssociative(
            "SELECT COLUMN_NAME AS name, DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_invoice' AND IS_NULLABLE = 'NO'",
        );
        foreach ($columns as $column) {
            $name = (string) $column['name'];
            if (isset($row[$name])) {
                continue;
            }
            $row[$name] = match ((string) $column['type']) {
                'binary' => Uuid::v7()->toBinary(),
                'date' => '2026-10-01',
                'datetime' => '2026-10-01 10:00:00',
                'decimal' => '0.00',
                'int', 'tinyint', 'smallint', 'bigint' => 0,
                default => 'x',
            };
        }
        $this->first->insert('purchase_invoice', $row);
    }
}
