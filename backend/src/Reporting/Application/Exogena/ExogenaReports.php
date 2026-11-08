<?php

namespace App\Reporting\Application\Exogena;

use App\Ledger\Application\Exogena\ExogenaConcepts;
use App\Ledger\Application\Exogena\ExogenaConceptView;
use App\Reporting\Application\Cartera\CarteraQueries;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The exógena working papers (observations PRD §4.7 d): the year's figures of the documents (ExogenaQueries), or the
 * cartera as it stood on 31/12 (CarteraQueries) for 1008 and 1009, each with the DIAN identification of its tercero,
 * the concept of its account (1001, 1007: Ledger's concept table, the longest prefix wins), and the cuantías menores
 * under the threshold grouped (ExogenaMinorAmounts). The screen and the files both read this.
 */
final class ExogenaReports
{
    public function __construct(
        private readonly ExogenaQueries $queries,
        private readonly CarteraQueries $cartera,
        private readonly ExogenaConcepts $concepts,
    ) {
    }

    public function report(Uuid $companyId, ExogenaFormat $format, int $year, Money $threshold): ExogenaReport
    {
        $amounts = match ($format) {
            ExogenaFormat::Receivables => $this->balances($companyId, CarteraSide::Clients, $year),
            ExogenaFormat::Payables => $this->balances($companyId, CarteraSide::Suppliers, $year),
            default => $this->queries->amounts($companyId, $format, $year),
        };
        $terceros = $this->queries->terceros($companyId, array_values(array_unique(array_map(static fn (ExogenaAmounts $a) => $a->terceroId, $amounts))));
        $table = $format->hasConcepts() ? $this->concepts->table($companyId) : [];

        $rows = [];
        foreach ($amounts as $a) {
            $tercero = $terceros[$a->terceroId] ?? null;
            if (null === $tercero) {
                continue;
            }
            $values = [];
            foreach ($format->valueColumns() as $column) {
                $values[$column] = Money::of($a->values[$column] ?? '0');
            }
            $concept = $format->hasConcepts() && null !== $a->accountCode ? ExogenaConceptView::conceptFor($table, $format->value, $a->accountCode) : null;
            $rows[] = new ExogenaRow($tercero, $format->hasConcepts() ? $a->accountCode : null, $concept, $values);
        }

        [$reported, $minor] = ExogenaMinorAmounts::split($rows, $threshold);
        $missingConcepts = $format->hasConcepts() ? \count(array_filter($rows, static fn (ExogenaRow $r) => null === $r->conceptCode)) : 0;
        $missingMunicipality = \count(array_unique(array_map(
            static fn (ExogenaRow $r) => (string) $r->tercero?->id,
            array_filter($reported, static fn (ExogenaRow $r) => true === $r->tercero?->lacksMunicipality()),
        )));

        return new ExogenaReport($year, $format, $threshold, $reported, $minor, $missingConcepts, $missingMunicipality);
    }

    /** @return list<ExogenaAmounts> what each tercero owed or was owed at the end of 31/12, largest first */
    private function balances(Uuid $companyId, CarteraSide $side, int $year): array
    {
        $rows = $this->cartera->summary($companyId, $side, new \DateTimeImmutable(\sprintf('%d-12-31', $year)), null);

        return array_map(static fn ($row) => new ExogenaAmounts($row->terceroId, null, ['balance' => $row->total]), $rows);
    }
}
