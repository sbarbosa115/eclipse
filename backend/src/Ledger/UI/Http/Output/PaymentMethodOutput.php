<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\PaymentMethodView;

final readonly class PaymentMethodOutput
{
    public function __construct(
        public string $id,
        public string $name,
        /** cash (contado) or credit (crédito) */
        public string $kind,
        public ?string $accountId,
        public ?string $accountCode,
        public ?string $accountName,
        public bool $active,
        public bool $standard,
    ) {
    }

    public static function of(PaymentMethodView $v): self
    {
        return new self($v->id, $v->name, $v->kind, $v->accountId, $v->accountCode, $v->accountName, $v->active, $v->standard);
    }
}
