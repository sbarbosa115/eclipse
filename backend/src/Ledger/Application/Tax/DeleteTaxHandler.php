<?php

namespace App\Ledger\Application\Tax;

use App\Ledger\Application\Port\CatalogAudit;
use App\Ledger\Application\Port\CatalogUsage;
use App\Ledger\Domain\Error\TaxInUse;
use App\Ledger\Domain\Error\TaxNotEditable;
use App\Ledger\Domain\Repository\TaxRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeleteTaxHandler implements CommandHandler
{
    public function __construct(
        private readonly TaxRepository $taxes,
        private readonly CatalogUsage $usage,
        private readonly CatalogAudit $audit,
    ) {
    }

    public function __invoke(DeleteTax $command): void
    {
        $tax = $this->taxes->get($command->companyId, $command->taxId);
        if ($tax->isNone()) {
            throw new TaxNotEditable();
        }
        if ($this->usage->taxIsUsed($command->companyId, $tax->id())) {
            throw new TaxInUse();
        }
        $this->taxes->remove($tax);
        $this->audit->record($command->companyId, $command->userId, 'tax.deleted', 'tax', $tax->id(), ['class' => $tax->taxClass()->value, 'kind' => $tax->kind()->value] + TaxSnapshots::of($tax));
    }
}
