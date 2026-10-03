<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\SalesInvoicePayment;

final readonly class SalesInvoicePaymentOutput
{
    public function __construct(
        public string $id,
        public int $position,
        public string $paymentMethodId,
        public string $methodName,
        /** cash (contado) or credit */
        public string $kind,
        public string $amount,
        public ?string $dueDate,
    ) {
    }

    public static function of(SalesInvoicePayment $p): self
    {
        return new self($p->id()->toRfc4122(), $p->position(), $p->paymentMethodId()->toRfc4122(), $p->methodName(), $p->kind()->value, $p->amount()->toString(), $p->dueDate()?->format('Y-m-d'));
    }
}
