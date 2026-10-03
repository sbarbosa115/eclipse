<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PayableView;

/** What the invoice owes on one crédito line. */
final readonly class PurchasePayableOutput
{
    public function __construct(
        public string $id,
        public string $amount,
        public string $balance,
        public string $dueDate,
        public bool $voided,
    ) {
    }

    public static function of(PayableView $v): self
    {
        return new self($v->id, $v->amount, $v->balance, $v->dueDate, $v->voided);
    }
}
