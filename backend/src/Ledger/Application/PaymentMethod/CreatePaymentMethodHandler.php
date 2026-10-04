<?php

namespace App\Ledger\Application\PaymentMethod;

use App\Ledger\Application\PostableAccounts;
use App\Ledger\Domain\Error\InvalidPaymentMethod;
use App\Ledger\Domain\Model\PaymentMethod;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Model\PaymentKind;
use Symfony\Component\Uid\Uuid;

final class CreatePaymentMethodHandler implements CommandHandler
{
    public function __construct(
        private readonly PaymentMethodRepository $methods,
        private readonly PostableAccounts $accounts,
        private readonly AuditTrail $audit,
    ) {
    }

    /**
     * @return Uuid the new method's id
     */
    public function __invoke(CreatePaymentMethod $command): Uuid
    {
        if ($this->methods->nameTaken($command->companyId, $command->name)) {
            throw new InvalidPaymentMethod('name', 'There is already a payment method with this name.');
        }
        $method = PaymentMethod::define(
            $command->companyId,
            $command->name,
            PaymentKind::tryFrom($command->kind) ?? throw new InvalidPaymentMethod('kind', 'Choose cash or credit.'),
            $this->accounts->forPaymentMethod($command->companyId, $command->accountId),
        );
        $this->methods->add($method);
        $this->audit->record($command->companyId, $command->userId, 'payment_method.created', 'payment_method', $method->id(), PaymentMethodSnapshots::of($method));

        return $method->id();
    }
}
