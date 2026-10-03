<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\Receivable;

/** A receivable the invoice opened: one per crédito line (cartera). */
final readonly class ReceivableOutput
{
    public function __construct(
        public string $id,
        public string $dueDate,
        public string $amount,
        public string $balance,
        public bool $voided,
    ) {
    }

    public static function of(Receivable $r): self
    {
        return new self($r->id()->toRfc4122(), $r->dueDate()->format('Y-m-d'), $r->amount()->toString(), $r->balance()->toString(), $r->isVoided());
    }
}
