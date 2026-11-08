<?php

namespace App\Ledger\Application\Command;

use App\Ledger\Application\PaymentMethod\PaymentMethodSnapshots;
use App\Ledger\Application\Port\AccountReferences;
use App\Ledger\Application\PostableAccounts;
use App\Ledger\Application\Tax\TaxSnapshots;
use App\Ledger\Domain\Error\AccountCodeTaken;
use App\Ledger\Domain\Error\ParentAccountNotFound;
use App\Ledger\Domain\Event\AccountSubdivided;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Repository\AccountRepository;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Ledger\Domain\Repository\PostingRuleRepository;
use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use Symfony\Component\Uid\Uuid;

/**
 * Adds a sub-account or auxiliar. The first one under an account that took movements subdivides it: the parent stops
 * receiving them (PostableAccounts), so what pointed at it moves to the new auxiliar, audited, unless the accountant
 * said not to (observations PRD §4.1, §9 Q-H). The ledger moves its own references here, in this transaction;
 * Catalog's products and Party's terceros move on AccountSubdivided, after the commit.
 */
final class AddAccountHandler implements CommandHandler
{
    private const REASON = 'account_subdivided';

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PostableAccounts $postable,
        private readonly PostingRuleRepository $rules,
        private readonly TaxRepository $taxes,
        private readonly PaymentMethodRepository $methods,
        private readonly AccountReferences $elsewhere,
        private readonly EventBus $events,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(AddAccount $command): AccountAdded
    {
        $parent = $this->accounts->byCode($command->companyId, trim($command->parentCode)) ?? throw new ParentAccountNotFound();
        $account = Account::under($parent, $command->code, $command->name, $command->usableOnPurchases);
        if (null !== $this->accounts->byCode($command->companyId, $account->code())) {
            throw new AccountCodeTaken();
        }
        // Asked before the child exists: did the parent take movements until now?
        $subdivides = $this->postable->isPostable($parent);
        $this->accounts->add($account);
        $this->audit->record($command->companyId, $command->userId, 'account.created', 'ledger_account', $account->id(), ['code' => $account->code(), 'name' => $account->name()]);

        if (!$subdivides || !$command->moveReferences) {
            return new AccountAdded($account->id());
        }

        return new AccountAdded($account->id(), $this->moveReferences($parent, $account, $command->userId));
    }

    private function moveReferences(Account $parent, Account $child, Uuid $userId): AccountMoves
    {
        $company = $parent->companyId();

        $concepts = [];
        foreach ($this->rules->pointingAt($company, $parent->id()) as $rule) {
            $rule->pointTo($child);
            $concepts[] = $rule->concept()->value;
            $this->audit->record($company, $userId, 'posting_rule.changed', 'posting_rule', $rule->id(), ['concept' => $rule->concept()->value, 'from' => $parent->code(), 'to' => $child->code(), 'reason' => self::REASON]);
        }

        $taxes = 0;
        foreach ($this->taxes->usingAccount($company, $parent->id()) as $tax) {
            $before = TaxSnapshots::of($tax);
            if ($tax->moveAccount($parent->id(), $child->id())) {
                ++$taxes;
                $this->audit->record($company, $userId, 'tax.updated', 'tax', $tax->id(), ['reason' => self::REASON, 'from' => $before, 'to' => TaxSnapshots::of($tax)]);
            }
        }

        $methods = 0;
        foreach ($this->methods->usingAccount($company, $parent->id()) as $method) {
            $before = PaymentMethodSnapshots::of($method);
            if ($method->moveAccount($parent->id(), $child->id())) {
                ++$methods;
                $this->audit->record($company, $userId, 'payment_method.updated', 'payment_method', $method->id(), ['reason' => self::REASON, 'from' => $before, 'to' => PaymentMethodSnapshots::of($method)]);
            }
        }

        // Counted now; Catalog and Party move them once this commits.
        $moves = new AccountMoves($concepts, $taxes, $methods, $this->elsewhere->products($company, $parent->id()), $this->elsewhere->terceros($company, $parent->id()));
        $this->events->publish(new AccountSubdivided($company->toRfc4122(), $parent->id()->toRfc4122(), $child->id()->toRfc4122(), $userId->toRfc4122()));

        return $moves;
    }
}
