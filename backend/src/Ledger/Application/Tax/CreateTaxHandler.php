<?php

namespace App\Ledger\Application\Tax;

use App\Ledger\Application\PostableAccounts;
use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Totals\TaxCalculation;
use Symfony\Component\Uid\Uuid;

final class CreateTaxHandler implements CommandHandler
{
    public function __construct(
        private readonly TaxRepository $taxes,
        private readonly PostableAccounts $accounts,
        private readonly AuditTrail $audit,
    ) {
    }

    /**
     * @return Uuid the new tax's id
     */
    public function __invoke(CreateTax $command): Uuid
    {
        if ($this->taxes->nameTaken($command->companyId, $command->name)) {
            throw new InvalidTaxDefinition('name', 'There is already a tax with this name.');
        }
        $tax = Tax::define(
            $command->companyId,
            $command->name,
            TaxClass::tryFrom($command->taxClass) ?? throw new InvalidTaxDefinition('tax_class', 'Choose a class of tax.'),
            TaxKind::tryFrom($command->kind) ?? throw new InvalidTaxDefinition('kind', 'Choose a kind of tax.'),
            TaxCalculation::tryFrom($command->calculation) ?? throw new InvalidTaxDefinition('calculation', 'Choose how the tax is calculated.'),
            $command->rate,
            $this->accounts->forTax($command->companyId, $command->salesAccountId, 'sales_account_id'),
            $this->accounts->forTax($command->companyId, $command->purchaseAccountId, 'purchase_account_id'),
            null === $command->validFrom || '' === $command->validFrom ? null : new \DateTimeImmutable($command->validFrom),
            null === $command->validTo || '' === $command->validTo ? null : new \DateTimeImmutable($command->validTo),
        );
        $this->taxes->add($tax);
        $this->audit->record($command->companyId, $command->userId, 'tax.created', 'tax', $tax->id(), ['class' => $tax->taxClass()->value, 'kind' => $tax->kind()->value] + TaxSnapshots::of($tax));

        return $tax->id();
    }
}
