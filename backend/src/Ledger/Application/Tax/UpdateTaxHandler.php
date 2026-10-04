<?php

namespace App\Ledger\Application\Tax;

use App\Ledger\Application\PostableAccounts;
use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Totals\TaxCalculation;

final class UpdateTaxHandler implements CommandHandler
{
    public function __construct(
        private readonly TaxRepository $taxes,
        private readonly PostableAccounts $accounts,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(UpdateTax $command): void
    {
        $tax = $this->taxes->get($command->companyId, $command->taxId);
        if ($this->taxes->nameTaken($command->companyId, $command->name, $tax->id())) {
            throw new InvalidTaxDefinition('name', 'There is already a tax with this name.');
        }
        $before = TaxSnapshots::of($tax);
        $tax->revise(
            $command->name,
            TaxCalculation::tryFrom($command->calculation) ?? throw new InvalidTaxDefinition('calculation', 'Choose how the tax is calculated.'),
            $command->rate,
            $this->accounts->forTax($command->companyId, $command->salesAccountId, 'sales_account_id'),
            $this->accounts->forTax($command->companyId, $command->purchaseAccountId, 'purchase_account_id'),
            null === $command->validFrom ? null : new \DateTimeImmutable($command->validFrom),
            null === $command->validTo ? null : new \DateTimeImmutable($command->validTo),
        );
        $this->audit->record($command->companyId, $command->userId, 'tax.updated', 'tax', $tax->id(), ['from' => $before, 'to' => TaxSnapshots::of($tax)]);
    }
}
