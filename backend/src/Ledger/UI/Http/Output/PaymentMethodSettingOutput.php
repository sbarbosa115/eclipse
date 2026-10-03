<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\PaymentMethodSettingView;

/** A payment method on the Formas de pago tab: what GET /payment-methods answers, plus whether a document uses it. */
final readonly class PaymentMethodSettingOutput
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
        /** A document uses it: it can only be deactivated. */
        public bool $inUse,
    ) {
    }

    public static function of(PaymentMethodSettingView $v): self
    {
        $m = $v->method;

        return new self($m->id, $m->name, $m->kind, $m->accountId, $m->accountCode, $m->accountName, $m->active, $m->standard, $v->inUse);
    }
}
