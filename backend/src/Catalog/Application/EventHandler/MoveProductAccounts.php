<?php

namespace App\Catalog\Application\EventHandler;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Event\AccountSubdivided;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The accountant subdivided an account in the chart: the products whose revenue or expense account it was move to the
 * new auxiliar, so their next document posts there (observations PRD §4.1), each move audited as product.updated.
 */
final class MoveProductAccounts implements EventHandler
{
    public function __construct(
        private readonly ProductRepository $products,
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

        foreach ($this->products->usingAccount($company, $from) as $product) {
            $before = ['revenue_account' => $product->revenueAccountId(), 'expense_account' => $product->expenseAccountId()];
            if (!$product->moveAccount($from, $to)) {
                continue;
            }
            $moved = array_keys(array_filter($before, static fn (?Uuid $id) => $id?->equals($from) ?? false));
            $this->audit->record($company, Uuid::fromString($event->byUserId), 'product.updated', 'product', $product->id(), [
                'reason' => 'account_subdivided',
                'from' => array_fill_keys($moved, $codes[0]),
                'to' => array_fill_keys($moved, $codes[1]),
            ]);
        }
    }
}
