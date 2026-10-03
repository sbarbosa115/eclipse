<?php

namespace App\Ledger\UI\Cli;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Posting\Side;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Shared\Domain\Accounting\PostingConcept as C;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Posts a few entries (a cash sale, a purchase of a service, a payment) for a local company, so the libro diario and
 * the reports have something to show before the document items land. Development only: real entries come from
 * documents (§5 invariant 2).
 */
#[AsCommand('app:ledger:demo-entries', 'Posts sample journal entries for a company, by NIT (development only).')]
final class DemoEntriesCommand extends Command
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly LedgerCatalog $catalog,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('nit', InputArgument::OPTIONAL, 'The company\'s NIT', '900123456');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('Sample entries are for development only.');

            return Command::FAILURE;
        }
        $db = $this->em->getConnection();
        $company = $db->fetchOne('SELECT id FROM company WHERE identification_number = ?', [(string) $input->getArgument('nit')]);
        $user = false === $company ? false : $db->fetchOne('SELECT id FROM app_user WHERE company_id = ? LIMIT 1', [$company]);
        if (!\is_string($company) || !\is_string($user)) {
            $io->error('No company (with a user) has that NIT.');

            return Command::FAILURE;
        }
        $companyId = Uuid::fromBinary($company);
        $userId = Uuid::fromBinary($user);
        $account = fn (string $code): Uuid => $this->catalog->accountIdByCode($companyId, $code) ?? throw new \RuntimeException("No account $code.");
        $line = static fn (Side $side, string $amount, C|Uuid $to) => $to instanceof Uuid
            ? EntryLine::toAccount($side, Money::of($amount), $to)
            : EntryLine::toConcept($side, Money::of($amount), $to);
        $month = new \DateTimeImmutable('first day of this month');

        $entries = [
            ['FE-DEMO-1', 'Venta de contado (ejemplo)', 0, [$line(Side::Debit, '1190000.00', $account('11050501')), $line(Side::Credit, '1000000.00', C::Revenue), $line(Side::Credit, '190000.00', C::VatGenerated)]],
            ['FC-DEMO-1', 'Compra de servicio (ejemplo)', 1, [$line(Side::Debit, '200000.00', $account('513595')), $line(Side::Debit, '38000.00', C::VatDeductible), $line(Side::Credit, '8000.00', C::WithholdingPracticed), $line(Side::Credit, '230000.00', C::Payables)]],
            ['RP-DEMO-1', 'Pago a proveedor (ejemplo)', 2, [$line(Side::Debit, '230000.00', C::Payables), $line(Side::Credit, '230000.00', $account('11100501'))]],
        ];
        $this->em->wrapInTransaction(function () use ($entries, $companyId, $userId, $month): void {
            foreach ($entries as [$number, $description, $day, $lines]) {
                $this->poster->post(new EntryDraft($companyId, $month->modify("+$day days"), 'demo', Uuid::v7(), $number, $description, $userId, $lines));
            }
        });
        $io->success(\sprintf('%d sample entries posted.', \count($entries)));

        return Command::SUCCESS;
    }
}
