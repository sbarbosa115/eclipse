<?php

namespace App\Ledger\Application\PaymentMethod;

use App\Ledger\Application\Port\CatalogAudit;
use App\Ledger\Application\PostableAccounts;
use App\Ledger\Domain\Error\InvalidPaymentMethod;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdatePaymentMethodHandler implements CommandHandler
{
    public function __construct(
        private readonly PaymentMethodRepository $methods,
        private readonly PostableAccounts $accounts,
        private readonly CatalogAudit $audit,
    ) {
    }

    public function __invoke(UpdatePaymentMethod $command): void
    {
        $method = $this->methods->get($command->companyId, $command->paymentMethodId);
        if ($this->methods->nameTaken($command->companyId, $command->name, $method->id())) {
            throw new InvalidPaymentMethod('name', 'There is already a payment method with this name.');
        }
        $before = PaymentMethodSnapshots::of($method);
        $method->revise($command->name, $this->accounts->forPaymentMethod($command->companyId, $command->accountId));
        $this->audit->record($command->companyId, $command->userId, 'payment_method.updated', 'payment_method', $method->id(), ['from' => $before, 'to' => PaymentMethodSnapshots::of($method)]);
    }
}
