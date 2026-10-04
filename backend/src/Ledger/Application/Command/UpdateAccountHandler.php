<?php

namespace App\Ledger\Application\Command;

use App\Ledger\Domain\Error\AccountInPostingRule;
use App\Ledger\Domain\Repository\AccountRepository;
use App\Ledger\Domain\Repository\PostingRuleRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class UpdateAccountHandler implements CommandHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PostingRuleRepository $rules,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(UpdateAccount $command): void
    {
        $account = $this->accounts->get($command->companyId, $command->accountId);
        $before = ['name' => $account->name(), 'active' => $account->isActive(), 'usable_on_purchases' => $account->isUsableOnPurchases()];

        $account->rename($command->name);
        if ($command->active) {
            $account->activate();
        } elseif ($account->isActive()) {
            if ($this->rules->pointsAt($command->companyId, $account->id())) {
                throw new AccountInPostingRule();
            }
            $account->deactivate();
        }
        $account->allowOnPurchases($command->usableOnPurchases);

        $after = ['name' => $account->name(), 'active' => $account->isActive(), 'usable_on_purchases' => $account->isUsableOnPurchases()];
        if ($after !== $before) {
            $this->audit->record($command->companyId, $command->userId, 'account.updated', 'ledger_account', $account->id(), ['code' => $account->code(), 'from' => $before, 'to' => $after]);
        }
    }
}
