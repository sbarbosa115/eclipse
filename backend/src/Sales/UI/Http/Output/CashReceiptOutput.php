<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\CashReceipt;

/** A recibo de caja, whole (§4.9): header, allocations, entries and who did what (§4.14). */
final readonly class CashReceiptOutput
{
    /**
     * @param list<CashReceiptAllocationOutput> $allocations
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

    public static function of(CashReceipt $r): self
    {
        return new self(
            $r->id()->toRfc4122(),
            $r->status()->value,
            $r->number(),
            $r->terceroId()->toRfc4122(),
            $r->terceroName(),
            $r->receiptDate()->format('Y-m-d'),
            $r->paymentMethodId()->toRfc4122(),
            $r->methodName(),
            $r->amount()->toString(),
            $r->notes(),
            array_map(CashReceiptAllocationOutput::of(...), $r->allocations()),
            $r->journalEntryId()?->toRfc4122(),
            $r->reversalEntryId()?->toRfc4122(),
            $r->createdBy()->toRfc4122(),
            $r->createdAt()->format(\DATE_ATOM),
            $r->voidedBy()?->toRfc4122(),
            $r->voidedAt()?->format(\DATE_ATOM),
            $r->voidReason(),
        );
    }
}
