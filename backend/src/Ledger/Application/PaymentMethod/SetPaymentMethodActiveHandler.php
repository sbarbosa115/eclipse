<?php

namespace App\Ledger\Application\PaymentMethod;

use App\Ledger\Application\Port\CatalogAudit;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Shared\Application\Command\CommandHandler;

final class SetPaymentMethodActiveHandler implements CommandHandler
{
    public function __construct(
        private readonly PaymentMethodRepository $methods,
        private readonly CatalogAudit $audit,
    ) {
    }

    public function __invoke(SetPaymentMethodActive $command): void
    {
        $method = $this->methods->get($command->companyId, $command->paymentMethodId);
        $command->active ? $method->activate() : $method->deactivate();
        $this->audit->record($command->companyId, $command->userId, $command->active ? 'payment_method.activated' : 'payment_method.deactivated', 'payment_method', $method->id(), ['name' => $method->name()]);
    }
}
