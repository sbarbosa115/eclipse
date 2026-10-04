<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * Save a recibo de caja (§4.9): it is emitted at once. With $send, *Guardar y enviar por mail*: its PDF is then
 * e-mailed to the client. The handler returns the new receipt's id.
 */
final readonly class ReceiveCash
{
    /**
     * @param list<CashReceiptAllocationData> $allocations
     */
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $terceroId,
        public \DateTimeImmutable $receiptDate,
        public Uuid $paymentMethodId,
        /** Valor recibido: pesos, a decimal string. */
        public string $amount,
        public ?string $notes,
        public array $allocations,
        public bool $send = false,
    ) {
    }
}
