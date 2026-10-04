<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Domain\Model\SupplierPayment;

/** A recibo de pago, whole (§4.11): header, allocations, entries and who did what (§4.14). */
final readonly class SupplierPaymentOutput
{
    /**
     * @param list<SupplierPaymentAllocationOutput> $allocations
     */
    public function __construct(
        public string $id,
        /** emitted or voided */
        public string $status,
        public string $number,
        public string $terceroId,
        public string $terceroName,
        public string $receiptDate,
        public string $paymentMethodId,
        public string $methodName,
        public string $amount,
        public ?string $notes,
        public array $allocations,
        public ?string $journalEntryId,
        public ?string $reversalEntryId,
        public string $createdBy,
        public string $createdAt,
        public ?string $voidedBy,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }

    public static function of(SupplierPayment $p): self
    {
        return new self(
            $p->id()->toRfc4122(),
            $p->status()->value,
            $p->number(),
            $p->terceroId()->toRfc4122(),
            $p->terceroName(),
            $p->receiptDate()->format('Y-m-d'),
            $p->paymentMethodId()->toRfc4122(),
            $p->methodName(),
            $p->amount()->toString(),
            $p->notes(),
            array_map(SupplierPaymentAllocationOutput::of(...), $p->allocations()),
            $p->journalEntryId()?->toRfc4122(),
            $p->reversalEntryId()?->toRfc4122(),
            $p->createdBy()->toRfc4122(),
            $p->createdAt()->format(\DATE_ATOM),
            $p->voidedBy()?->toRfc4122(),
            $p->voidedAt()?->format(\DATE_ATOM),
            $p->voidReason(),
        );
    }
}
