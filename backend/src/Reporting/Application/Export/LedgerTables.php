<?php

namespace App\Reporting\Application\Export;

use App\Ledger\Application\Query\JournalEntryView;
use App\Ledger\Application\Query\JournalFilter;
use App\Ledger\Application\Query\JournalQueries;
use App\Ledger\Application\Report\LedgerReports;
use App\Ledger\Application\Report\StatementSection;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The ledger's four books as exportable tables. The numbers come from the ledger's own query services
 * (JournalQueries, LedgerReports), never from SQL of ours: an export shows exactly what the screen shows.
 */
final class LedgerTables
{
    /** Entries read per page while a libro diario streams. */
    private const PAGE = 200;

    public function __construct(
        private readonly JournalQueries $journal,
        private readonly LedgerReports $reports,
    ) {
    }

    /**
     * One row per entry line. The CSV streams page by page, so the cap is checked up front, on entries: an entry has
     * at least two lines, so a row cap of N allows N/2 entries (a stream never fails halfway). Entries with many lines
     * can take a CSV somewhat past N rows; a PDF counts its rows exactly (ReportPdf).
     *
     * @throws ExportTooLarge
     */
    public function journal(Uuid $companyId, JournalFilter $filter, int $limit): TabularReport
    {
        $first = $this->journal->page($companyId, $filter, 1, 1);
        if ($first->total * 2 > $limit) {
            throw new ExportTooLarge($first->total * 2, $limit);
        }
        $debit = $credit = Money::zero();
        $rows = (function () use ($companyId, $filter, &$debit, &$credit): \Generator {
            for ($page = 1;; ++$page) {
                $result = $this->journal->page($companyId, $filter, $page, self::PAGE);
                foreach ($result->items as $entry) {
                    foreach (self::lines($entry) as $row) {
                        $debit = $debit->plus(Money::of($row[7]));
                        $credit = $credit->plus(Money::of($row[8]));
                        yield $row;
                    }
                }
                if ($page * self::PAGE >= $result->total || [] === $result->items) {
                    return;
                }
            }
        })();

        return new TabularReport(
            'Libro diario',
            'libro-diario'.self::range($filter->from, $filter->to, true),
            [
                new ReportColumn('Fecha', ColumnKind::Date),
                new ReportColumn('Asiento', ColumnKind::Number),
                new ReportColumn('Documento'),
                new ReportColumn('Cuenta'),
                new ReportColumn('Nombre de la cuenta'),
                new ReportColumn('Tercero'),
                new ReportColumn('Descripción'),
                new ReportColumn('Débito', ColumnKind::Money),
                new ReportColumn('Crédito', ColumnKind::Money),
            ],
            $rows,
            static fn () => ['Total', '', '', '', '', '', '', $debit->toString(), $credit->toString()],
            self::subtitle($filter->from, $filter->to, $filter->accountCode, null !== $filter->terceroId),
        );
    }

    /** @throws ExportTooLarge */
    public function trialBalance(Uuid $companyId, ?\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): TabularReport
    {
        $balance = $this->reports->trialBalance($companyId, $from, $to);
        if (\count($balance->rows) > $limit) {
            throw new ExportTooLarge(\count($balance->rows), $limit);
        }
        $rows = array_map(static fn ($r) => [$r->code, $r->name, $r->opening, $r->debit, $r->credit, $r->closing], $balance->rows);

        return new TabularReport(
            'Balance de prueba',
            'balance-de-prueba'.self::range($from, $to, true),
            [
                new ReportColumn('Cuenta'),
                new ReportColumn('Nombre'),
                new ReportColumn('Saldo anterior', ColumnKind::Money),
                new ReportColumn('Débito', ColumnKind::Money),
                new ReportColumn('Crédito', ColumnKind::Money),
                new ReportColumn('Nuevo saldo', ColumnKind::Money),
            ],
            $rows,
            static fn () => ['Total', '', '', $balance->totalDebit, $balance->totalCredit, ''],
            self::subtitle($from, $to),
        );
    }

    /** @throws ExportTooLarge */
    public function incomeStatement(Uuid $companyId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): TabularReport
    {
        $statement = $this->reports->incomeStatement($companyId, $from, $to);
        $rows = self::statementRows($statement->sections, $limit);
        $rows[] = ['', 'Utilidad bruta (ingresos - costos)', $statement->grossProfit];

        return new TabularReport(
            'Estado de resultados',
            'estado-de-resultados'.self::range($from, $to, true),
            [new ReportColumn('Cuenta'), new ReportColumn('Nombre'), new ReportColumn('Valor', ColumnKind::Money)],
            $rows,
            static fn () => ['', 'Resultado del período', $statement->netIncome],
            self::subtitle($from, $to),
        );
    }

    /** @throws ExportTooLarge */
    public function balanceSheet(Uuid $companyId, \DateTimeImmutable $date, int $limit): TabularReport
    {
        $sheet = $this->reports->balanceSheet($companyId, $date);
        $rows = self::statementRows($sheet->sections, $limit);
        $rows[] = ['', 'Resultado del ejercicio (dentro del patrimonio)', $sheet->currentEarnings];

        return new TabularReport(
            'Balance general',
            'balance-general-'.$date->format('Y-m-d'),
            [new ReportColumn('Cuenta'), new ReportColumn('Nombre'), new ReportColumn('Valor', ColumnKind::Money)],
            $rows,
            static fn () => ['', 'Total pasivo y patrimonio', Money::of($sheet->totalLiabilities)->plus(Money::of($sheet->totalEquity))->toString()],
            ['Al '.$date->format('d/m/Y')],
        );
    }

    /**
     * @param list<StatementSection> $sections
     *
     * @return list<list<string>>
     */
    private static function statementRows(array $sections, int $limit): array
    {
        $rows = [];
        foreach ($sections as $section) {
            $rows[] = [$section->code, $section->name, $section->total];
            foreach ($section->lines as $line) {
                $rows[] = [$line->code, $line->name, $line->amount];
            }
        }
        if (\count($rows) > $limit) {
            throw new ExportTooLarge(\count($rows), $limit);
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private static function lines(JournalEntryView $entry): array
    {
        return array_map(static fn ($l) => [
            $entry->date,
            (string) $entry->number,
            $entry->sourceNumber,
            $l->accountCode,
            $l->accountName,
            $l->terceroName ?? '',
            $l->description ?? $entry->description,
            $l->debit,
            $l->credit,
        ], $entry->lines);
    }

    private static function range(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $prefixed): string
    {
        $parts = array_filter([$from?->format('Y-m-d'), $to?->format('Y-m-d')]);

        return [] === $parts ? '' : ($prefixed ? '-' : '').implode('-a-', $parts);
    }

    /** @return list<string> */
    private static function subtitle(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, ?string $account = null, bool $tercero = false): array
    {
        $period = match (true) {
            null !== $from && null !== $to => 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y'),
            null !== $to => 'Hasta el '.$to->format('d/m/Y'),
            null !== $from => 'Desde el '.$from->format('d/m/Y'),
            default => null,
        };

        return array_values(array_filter([$period, null === $account ? null : 'Cuenta '.$account, $tercero ? 'Filtrado por tercero' : null]));
    }
}
