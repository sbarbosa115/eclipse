<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * Save a recibo de pago (§4.11): it is emitted at once. With $send, *Guardar y enviar*: its PDF is then e-mailed to the
 * supplier. The handler returns the new payment's id.
 */
final readonly class PaySupplier
{
    /**
     * @param list<SupplierPaymentAllocationData> $allocations
     */
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $terceroId,
        public \DateTimeImmutable $receiptDate,
        public Uuid $paymentMethodId,
        /** Valor pagado: pesos, a decimal string. */
        public string $amount,
        public ?string $notes,
        public array $allocations,
        public bool $send = false,
    ) {
    }
}
