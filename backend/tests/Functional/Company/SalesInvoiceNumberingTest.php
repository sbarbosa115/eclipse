<?php

namespace App\Tests\Functional\Company;

use App\Company\Application\Numbering\SalesInvoiceNumbering;
use App\Company\Domain\Error\ResolutionExhausted;
use App\Company\Domain\Error\ResolutionInactive;
use App\Company\Domain\Error\ResolutionMissing;
use App\Company\Domain\Model\InvoicingMode;
use App\Company\Domain\Model\InvoicingResolution;
use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Model\SeriesKind;
use App\Tests\Support\BuildsNumbering;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The authorised number of a sales invoice (§4.1, §4.8): what emission asks for inside its transaction.
 */
final class SalesInvoiceNumberingTest extends KernelTestCase
{
    use BuildsNumbering;

    private EntityManagerInterface $em;
    private SalesInvoiceNumbering $numbering;
    private Uuid $companyId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->numbering = self::salesInvoiceNumbering($this->em);
        $this->companyId = Uuid::v7();
        // The company row is not needed by the numbering: its foreign keys are the contract's concern.
        $this->connection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        // The emission's transaction (the command bus opens it): the row lock needs one.
        $this->connection()->beginTransaction();
        $this->em->persist(new NumberingSeries($this->companyId, SeriesKind::SalesInvoiceInternal, 'FVI', 1));
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->connection()->rollBack();
        $this->connection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }

    private function resolution(int $from = 1, int $to = 100, string $start = '2026-01-01', string $end = '2026-12-31'): InvoicingResolution
    {
        $r = InvoicingResolution::define($this->companyId, '18760000001', 'SETP', $from, $to, new \DateTimeImmutable($start), new \DateTimeImmutable($end), InvoicingMode::Electronic, false);
        $this->em->persist($r);
        $this->em->flush();

        return $r;
    }

    private static function on(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    public function testTheFirstInvoiceGetsDesdeAndTheInternalConsecutive(): void
    {
        $r = $this->resolution(1500, 1600);

        $number = $this->numbering->next($this->companyId, self::on('2026-03-01'));

        self::assertSame('SETP-1500', $number->authorised->formatted());
        self::assertSame('FVI-1', $number->internal->formatted(), 'The internal consecutive (§4.8) is taken alongside.');
        self::assertTrue($number->resolutionId->equals($r->id()), 'The invoice records which resolution authorised it.');
    }

    public function testTwoNumbersAreNeverTheSame(): void
    {
        $this->resolution();

        $numbers = [];
        for ($i = 0; $i < 25; ++$i) {
            $n = $this->numbering->next($this->companyId, self::on('2026-03-01'));
            $numbers[] = $n->authorised->sequence;
            $internal[] = $n->internal->sequence;
        }
        $this->em->flush();
        $this->em->clear();

        self::assertSame(range(1, 25), $numbers, 'Consecutive, without gaps or repeats.');
        self::assertCount(25, array_unique($internal), 'The internal consecutive is as unique.');
        self::assertSame(26, $this->connection()->fetchOne('SELECT next_number FROM invoicing_resolution WHERE company_id = ?', [$this->companyId->toBinary()]), 'The stored consecutive moved with every number.');
    }

    public function testASecondRequestAfterTheFirstNeverRepeatsItsNumber(): void
    {
        $this->resolution();
        $this->em->flush();
        // A second request starts with an empty identity map and reads the row the first one wrote.
        $first = $this->numbering->next($this->companyId, self::on('2026-03-01'));
        $this->em->flush();
        $this->em->clear();
        $second = self::salesInvoiceNumbering($this->em)->next($this->companyId, self::on('2026-03-01'));

        self::assertNotSame($first->authorised->sequence, $second->authorised->sequence, 'A second request reloads the row after the first one took its number.');
    }

    public function testARolledBackTransactionGivesItsNumberBack(): void
    {
        $this->resolution();
        $this->numbering->next($this->companyId, self::on('2026-03-01'));
        $this->em->flush();

        $this->connection()->beginTransaction(); // a savepoint inside the emission's transaction
        $lost = $this->numbering->next($this->companyId, self::on('2026-03-01'));
        $this->em->flush();
        $this->connection()->rollBack();
        $this->em->clear();

        self::assertSame(2, $lost->authorised->sequence);
        $again = $this->numbering->next($this->companyId, self::on('2026-03-01'));
        self::assertSame(2, $again->authorised->sequence, 'The emission failed after numbering: number 2 was never used and is handed out again.');
        self::assertSame(2, $again->internal->sequence, 'So is the internal consecutive.');
    }

    public function testItRefusesOutsideTheResolutionsDates(): void
    {
        $this->resolution();

        foreach (['2025-12-31', '2027-01-01'] as $date) {
            try {
                $this->numbering->next($this->companyId, self::on($date));
                self::fail("$date is outside the resolution.");
            } catch (ResolutionInactive $e) {
                self::assertSame('resolution_inactive', $e->errorCode());
            }
        }
        $this->em->flush();
        self::assertSame(1, $this->numbering->next($this->companyId, self::on('2026-12-31'))->authorised->sequence, 'The refusals took no number; the last day still works.');
    }

    public function testItRefusesPastHastaAndTheInternalNumberIsNotWasted(): void
    {
        $this->resolution(1, 2);
        $this->numbering->next($this->companyId, self::on('2026-03-01'));
        $this->numbering->next($this->companyId, self::on('2026-03-01'));

        try {
            $this->numbering->next($this->companyId, self::on('2026-03-01'));
            self::fail('Hasta was 2.');
        } catch (ResolutionExhausted $e) {
            self::assertSame('resolution_exhausted', $e->errorCode());
        }
        $this->em->flush();
        self::assertSame(3, $this->connection()->fetchOne('SELECT next_number FROM numbering_series WHERE company_id = ? AND kind = ?', [$this->companyId->toBinary(), 'sales_invoice_internal']), 'The internal series stayed at 3: the refusal came before it was touched.');
    }

    public function testItRefusesWithoutAResolution(): void
    {
        $this->expectException(ResolutionMissing::class);

        $this->numbering->next($this->companyId, self::on('2026-03-01'));
    }

    public function testAnotherCompanysResolutionIsNotUsed(): void
    {
        $this->resolution();

        $this->expectException(ResolutionMissing::class);
        $this->numbering->next(Uuid::v7(), self::on('2026-03-01'));
    }
}
