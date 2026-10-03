<?php

namespace App\Ledger\Application\Seed;

use App\Ledger\Application\Port\ChartWriter;
use App\Ledger\Application\Port\PucCatalog;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\PostingRule;
use App\Shared\Application\Company\CompanyProvisioner;
use App\Shared\Domain\Accounting\PostingConcept;
use Symfony\Component\Uid\Uuid;

/**
 * §4.1: a new company receives the PUC chart (classes, groups, cuentas and subcuentas), Mustang's auxiliares, the
 * default posting rules (§5) and open books. Runs first among the ledger's provisioners: taxes and payment methods
 * (priority 50) point at these accounts.
 */
final class ProvisionLedger implements CompanyProvisioner
{
    public function __construct(
        private readonly PucCatalog $puc,
        private readonly ChartWriter $writer,
    ) {
    }

    public function provision(Uuid $companyId): void
    {
        /** @var array<array-key, Account> $chart */
        $chart = [];
        foreach ($this->puc->accounts() as $seed) {
            $parent = self::parentCode($seed->code);
            $chart[$seed->code] = new Account($companyId, $seed->code, $seed->name, $seed->nature, $parent, true, Account::isCostOrExpense($seed->code));
        }
        foreach (MustangChart::ACCOUNTS as $code => [$name, $parent]) {
            $code = (string) $code;
            $chart[$code] = Account::under($chart[$parent] ?? throw new \LogicException("The PUC has no $parent."), $code, $name);
        }

        $rules = [];
        foreach (MustangChart::defaultRules() as $concept => $code) {
            $account = $chart[$code] ?? throw new \LogicException("The chart has no $code.");
            $rules[] = new PostingRule($companyId, PostingConcept::from($concept), $account->id());
        }

        $this->writer->write($companyId, array_values($chart), $rules);
    }

    public static function getPriority(): int
    {
        return 100;
    }

    /** The PUC's levels: clase 1 digit, grupo 2, cuenta 4, subcuenta 6. */
    private static function parentCode(string $code): ?string
    {
        return match (\strlen($code)) {
            1 => null,
            2 => substr($code, 0, 1),
            default => substr($code, 0, \strlen($code) - 2),
        };
    }
}
