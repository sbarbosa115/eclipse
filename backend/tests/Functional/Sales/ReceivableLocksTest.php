<?php

namespace App\Tests\Functional\Sales;

use App\Sales\Domain\Model\Receivable;
use App\Sales\Infrastructure\Persistence\DoctrineReceivableLocks;
use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two receipts for the same receivable must not both pass the balance check: the receipt's handler locks the
 * receivables it allocates to (SELECT … FOR UPDATE) and reads their balances afresh, inside its transaction.
 *
 * The tests' own connection lives in one transaction that is never committed (DAMA), so a second request cannot be
 * simulated on it. These tests open two real connections of their own, like two PHP processes, commit their rows
 * and remove them afterwards.
 */
final class ReceivableLocksTest extends KernelTestCase
{
    private Connection $first;
    private Connection $second;
    private Uuid $company;
    private Uuid $receivable;

    protected function setUp(): void
    {
        self::bootKernel();
        $params = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams();
        $this->first = DriverManager::getConnection($params);
        $this->second = DriverManager::getConnection($params);
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->company = Uuid::v7();
        $this->receivable = Uuid::v7();
        // A receivable of a company, client and invoice that exist only for this test: the foreign keys are not its
        // concern.
        $this->first->executeStatement('SET SESSION foreign_key_checks = 0');
        $this->first->insert('receivable', [
            'id' => $this->receivable->toBinary(), 'company_id' => $this->company->toBinary(), 'invoice_id' => Uuid::v7()->toBinary(),
            'invoice_number' => 'FE-1', 'tercero_id' => Uuid::v7()->toBinary(), 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
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
        $this->first->delete('receivable', ['company_id' => $this->company->toBinary()]);
        $this->first->close();
        $this->second->close();
        parent::tearDown();
    }

    private function locksOn(Connection $connection): DoctrineReceivableLocks
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        return new DoctrineReceivableLocks(new EntityManager($connection, $em->getConfiguration()));
    }

    public function testASecondReceiptWaitsForTheFirst(): void
    {
        $this->first->beginTransaction();
        $locked = $this->locksOn($this->first)->lockForCollection($this->company, [$this->receivable]);
        self::assertArrayHasKey($this->receivable->toRfc4122(), $locked);

        $this->second->beginTransaction();
        try {
            $this->locksOn($this->second)->lockForCollection($this->company, [$this->receivable]);
            self::fail('The second receipt read the balance while the first still held the receivable.');
        } catch (LockWaitTimeoutException) {
            // Waited, as a second request would until the first commits.
        }
        $this->second->rollBack();

        $this->first->rollBack();
        $this->second->beginTransaction();
        self::assertCount(1, $this->locksOn($this->second)->lockForCollection($this->company, [$this->receivable]), 'Once the first one ends, the second goes on.');
    }

    public function testWithoutTheLockASecondReaderWouldNotWait(): void
    {
        // The control: a plain read takes no lock, so the test above fails for the right reason if the lock goes.
        $this->first->beginTransaction();
        $this->first->fetchOne('SELECT balance FROM receivable WHERE id = ?', [$this->receivable->toBinary()]);

        $this->second->beginTransaction();
        self::assertCount(1, $this->locksOn($this->second)->lockForCollection($this->company, [$this->receivable]));
    }

    public function testTheSecondReceiptSeesTheBalanceTheFirstLeft(): void
    {
        $secondLocks = $this->locksOn($this->second);
        $this->second->beginTransaction();
        // The second request had already read the receivable (e.g. to show it) before the first one committed.
        $secondEm = (new \ReflectionProperty(DoctrineReceivableLocks::class, 'em'))->getValue($secondLocks);
        \assert($secondEm instanceof EntityManagerInterface);
        $stale = $secondEm->find(Receivable::class, $this->receivable);
        \assert($stale instanceof Receivable);
        self::assertSame('1000.00', $stale->balance()->toString());
        $this->second->rollBack();

        $this->first->beginTransaction();
        $firstEm = new EntityManager($this->first, self::getContainer()->get(EntityManagerInterface::class)->getConfiguration());
        $mine = (new DoctrineReceivableLocks($firstEm))->lockForCollection($this->company, [$this->receivable])[$this->receivable->toRfc4122()];
        $mine->apply(Money::of('800.00'));
        $firstEm->flush();
        $this->first->commit();

        $this->second->beginTransaction();
        $fresh = $secondLocks->lockForCollection($this->company, [$this->receivable])[$this->receivable->toRfc4122()];
        self::assertSame('200.00', $fresh->balance()->toString(), 'Locked and read again: 800 are gone, so a second allocation of 800 is refused.');
    }

    public function testAnotherCompanysReceivableIsLeftOut(): void
    {
        $this->first->beginTransaction();

        self::assertSame([], $this->locksOn($this->first)->lockForCollection(Uuid::v7(), [$this->receivable]), 'Unknown to another company: the receipt refuses it by field.');
    }
}
