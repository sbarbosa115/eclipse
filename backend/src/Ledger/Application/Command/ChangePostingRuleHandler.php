<?php

namespace App\Ledger\Application\Command;

use App\Ledger\Domain\Error\PostingRuleNotFound;
use App\Ledger\Domain\Repository\AccountRepository;
use App\Ledger\Domain\Repository\PostingRuleRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class ChangePostingRuleHandler implements CommandHandler
{
    public function __construct(
        private readonly PostingRuleRepository $rules,
        private readonly AccountRepository $accounts,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(ChangePostingRule $command): void
    {
        $rule = $this->rules->forConcept($command->companyId, $command->concept) ?? throw new PostingRuleNotFound();
        $from = $this->accounts->get($command->companyId, $rule->accountId());
        $to = $this->accounts->get($command->companyId, $command->accountId);

        $rule->pointTo($to);

        if ($from->code() !== $to->code()) {
            $this->audit->record($command->companyId, $command->userId, 'posting_rule.changed', 'posting_rule', $rule->id(), ['concept' => $command->concept->value, 'from' => $from->code(), 'to' => $to->code()]);
        }
    }
}
