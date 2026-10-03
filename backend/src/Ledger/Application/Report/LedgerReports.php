<?php

namespace App\Ledger\Application\Report;

use App\Ledger\Application\Query\LedgerMovements;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The balance de prueba (§4.13) and the two statements derived from it (§9 Q25). Sums are read per account from the
 * journal and rolled up here to every parent (subcuenta, cuenta, grupo, clase), in decimal arithmetic.
 */
final class LedgerReports
{
    private const INCOME_SECTIONS = ['4', '6', '7', '5'];
    private const BALANCE_SECTIONS = ['1', '2', '3'];

    public function __construct(private readonly LedgerMovements $movements)
    {
    }

    public function trialBalance(Uuid $companyId, ?\DateTimeImmutable $from, \DateTimeImmutable $to): TrialBalance
    {
        $movements = $this->movements->byAccount($companyId, $from, $to);

        /** @var array<string, array{Money, Money, Money}> $sums opening, débito, crédito per code and parent */
        $sums = [];
        $totalDebit = $totalCredit = Money::zero();
        foreach ($movements as $m) {
            $totalDebit = $totalDebit->plus($m->debit);
            $totalCredit = $totalCredit->plus($m->credit);
            foreach (self::selfAndParents($m->code) as $code) {
                [$opening, $debit, $credit] = $sums[$code] ?? [Money::zero(), Money::zero(), Money::zero()];
                $sums[$code] = [$opening->plus($m->opening), $debit->plus($m->debit), $credit->plus($m->credit)];
            }
        }
        $sums = array_filter($sums, static fn (array $s) => !($s[0]->isZero() && $s[1]->isZero() && $s[2]->isZero()));
        ksort($sums, \SORT_STRING);

        $accounts = $this->movements->accounts($companyId, array_map(strval(...), array_keys($sums)));
        $rows = [];
        foreach ($sums as $code => [$opening, $debit, $credit]) {
            $code = (string) $code;
            $account = $accounts[$code] ?? ['name' => $code, 'level' => '', 'nature' => 'debit'];
            $rows[] = new TrialBalanceRow($code, $account['name'], $account['level'], $account['nature'], $opening->toString(), $debit->toString(), $credit->toString(), $opening->plus($debit)->minus($credit)->toString());
        }

        return new TrialBalance($from?->format('Y-m-d'), $to->format('Y-m-d'), $rows, $totalDebit->toString(), $totalCredit->toString(), $totalDebit->equals($totalCredit));
    }

    public function incomeStatement(Uuid $companyId, \DateTimeImmutable $from, \DateTimeImmutable $to): IncomeStatement
    {
        $balance = $this->trialBalance($companyId, $from, $to);
        // What the period moved, on each section's own side: crédito for ingresos, débito for costos and gastos.
        $amount = static fn (TrialBalanceRow $r): Money => '4' === $r->code[0]
            ? Money::of($r->credit)->minus(Money::of($r->debit))
            : Money::of($r->debit)->minus(Money::of($r->credit));
        $sections = $this->sections($companyId, $balance, self::INCOME_SECTIONS, $amount);

        $revenue = Money::of($sections['4']->total);
        $costs = Money::of($sections['6']->total)->plus(Money::of($sections['7']->total));
        $expenses = Money::of($sections['5']->total);
        $gross = $revenue->minus($costs);

        return new IncomeStatement($from->format('Y-m-d'), $to->format('Y-m-d'), array_values($sections), $revenue->toString(), $costs->toString(), $expenses->toString(), $gross->toString(), $gross->minus($expenses)->toString());
    }

    public function balanceSheet(Uuid $companyId, \DateTimeImmutable $date): BalanceSheet
    {
        $balance = $this->trialBalance($companyId, null, $date);
        // Closing balances on each side: débito for activo, crédito for pasivo and patrimonio.
        $amount = static fn (TrialBalanceRow $r): Money => '1' === $r->code[0] ? Money::of($r->closing) : Money::of($r->closing)->negated();
        $sections = $this->sections($companyId, $balance, self::BALANCE_SECTIONS, $amount);

        $earnings = Money::zero();
        foreach ($balance->rows as $row) {
            if (1 === \strlen($row->code) && \in_array($row->code, self::INCOME_SECTIONS, true)) {
                $earnings = $earnings->minus(Money::of($row->closing));
            }
        }
        $assets = Money::of($sections['1']->total);
        $liabilities = Money::of($sections['2']->total);
        $equity = Money::of($sections['3']->total)->plus($earnings);

        return new BalanceSheet($date->format('Y-m-d'), array_values($sections), $earnings->toString(), $assets->toString(), $liabilities->toString(), $equity->toString(), $assets->equals($liabilities->plus($equity)));
    }

    /**
     * @param list<string>                     $classes
     * @param callable(TrialBalanceRow): Money $amount
     *
     * @return array<array-key, StatementSection> by class code
     */
    private function sections(Uuid $companyId, TrialBalance $balance, array $classes, callable $amount): array
    {
        $names = $this->movements->accounts($companyId, $classes);
        $sections = [];
        foreach ($classes as $class) {
            $total = Money::zero();
            $lines = [];
            foreach ($balance->rows as $row) {
                if ($row->code[0] !== $class) {
                    continue;
                }
                $value = $amount($row);
                if ($row->code === $class) {
                    $total = $value;
                } elseif (\in_array(\strlen($row->code), [2, 4], true) && !$value->isZero()) {
                    $lines[] = new StatementLine($row->code, $row->name, $row->level, $value->toString());
                }
            }
            $sections[$class] = new StatementSection($class, $names[$class]['name'] ?? $class, $total->toString(), $lines);
        }

        return $sections;
    }

    /**
     * A code and every level above it: 11050501 → 1, 11, 1105, 110505, 11050501.
     *
     * @return list<string>
     */
    private static function selfAndParents(string $code): array
    {
        $codes = [substr($code, 0, 1)];
        for ($length = 2; $length <= \strlen($code); $length += 2) {
            $codes[] = substr($code, 0, $length);
        }

        return array_values(array_unique($codes));
    }
}
