<?php

namespace App\Ledger\Application\Command;

use App\Ledger\Application\Port\AuditTrail;
use App\Ledger\Domain\Error\AccountCodeTaken;
use App\Ledger\Domain\Error\ParentAccountNotFound;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Repository\AccountRepository;
use App\Shared\Application\Command\CommandHandler;
use Symfony\Component\Uid\Uuid;

final class AddAccountHandler implements CommandHandler
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly AuditTrail $audit,
    ) {
    }

    /** @return Uuid the new account's id */
    public function __invoke(AddAccount $command): Uuid
    {
        $parent = $this->accounts->byCode($command->companyId, trim($command->parentCode)) ?? throw new ParentAccountNotFound();
        $account = Account::under($parent, $command->code, $command->name, $command->usableOnPurchases);
        if (null !== $this->accounts->byCode($command->companyId, $account->code())) {
            throw new AccountCodeTaken();
        }
        $this->accounts->add($account);
        $this->audit->record($command->companyId, $command->userId, 'account.created', 'ledger_account', $account->id(), ['code' => $account->code(), 'name' => $account->name()]);

        return $account->id();
    }
}
