<?php

namespace App\Party\Application\EventHandler;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Event\AccountSubdivided;
use App\Party\Domain\Repository\TerceroRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The accountant subdivided an account in the chart: the terceros whose own receivable or payable account it was move
 * to the new auxiliar, so their next document posts there (observations PRD §4.1), each move audited as
 * tercero.updated.
 */
final class MoveTerceroAccounts implements EventHandler
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly LedgerCatalog $ledger,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(AccountSubdivided $event): void
    {
        $company = Uuid::fromString($event->companyId);
        $from = Uuid::fromString($event->parentAccountId);
        $to = Uuid::fromString($event->childAccountId);
        $codes = [$this->ledger->account($company, $from)->code, $this->ledger->account($company, $to)->code];

        foreach ($this->terceros->usingAccount($company, $from) as $tercero) {
            $before = ['receivable_account' => $tercero->receivableAccountId(), 'payable_account' => $tercero->payableAccountId()];
            if (!$tercero->moveAccount($from, $to)) {
                continue;
            }
            $moved = array_keys(array_filter($before, static fn (?Uuid $id) => $id?->equals($from) ?? false));
            $this->audit->record($company, Uuid::fromString($event->byUserId), 'tercero.updated', 'tercero', $tercero->id(), [
                'reason' => 'account_subdivided',
                'from' => array_fill_keys($moved, $codes[0]),
                'to' => array_fill_keys($moved, $codes[1]),
            ]);
        }
    }
}
