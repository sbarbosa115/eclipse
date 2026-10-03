<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PurchaseInvoicePaymentView;

final readonly class PurchaseInvoicePaymentOutput
{
    public function __construct(
        public string $id,
        public int $position,
        public string $paymentMethodId,
        public string $methodName,
        /** cash or credit */
        public string $kind,
        public string $amount,
        public ?string $dueDate,
    ) {
    }

    public static function of(PurchaseInvoicePaymentView $v): self
    {
        return new self($v->id, $v->position, $v->paymentMethodId, $v->methodName, $v->kind, $v->amount, $v->dueDate);
    }
}
