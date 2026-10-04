<?php

namespace App\Ledger\Application\Tax;

use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class SetTaxActiveHandler implements CommandHandler
{
    public function __construct(
        private readonly TaxRepository $taxes,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(SetTaxActive $command): void
    {
        $tax = $this->taxes->get($command->companyId, $command->taxId);
        $command->active ? $tax->activate() : $tax->deactivate();
        $this->audit->record($command->companyId, $command->userId, $command->active ? 'tax.activated' : 'tax.deactivated', 'tax', $tax->id(), ['name' => $tax->name()]);
    }
}
