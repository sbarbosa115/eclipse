<?php

namespace App\Ledger\Application\PaymentMethod;

use App\Ledger\Application\Port\CatalogUsage;
use App\Ledger\Domain\Error\PaymentMethodInUse;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class DeletePaymentMethodHandler implements CommandHandler
{
    public function __construct(
        private readonly PaymentMethodRepository $methods,
        private readonly CatalogUsage $usage,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(DeletePaymentMethod $command): void
    {
        $method = $this->methods->get($command->companyId, $command->paymentMethodId);
        if ($this->usage->paymentMethodIsUsed($command->companyId, $method->id())) {
            throw new PaymentMethodInUse();
        }
        $this->methods->remove($method);
        $this->audit->record($command->companyId, $command->userId, 'payment_method.deleted', 'payment_method', $method->id(), PaymentMethodSnapshots::of($method));
    }
}
