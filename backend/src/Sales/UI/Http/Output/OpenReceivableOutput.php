<?php

namespace App\Sales\UI\Http\Output;

use App\Sales\Domain\Model\Receivable;

/** A receivable the client still owes (§4.9): factura, fecha, vencimiento, valor, saldo. */
final readonly class OpenReceivableOutput
{
    public function __construct(
        /** The receivable: what an allocation names. */
        public string $id,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $issueDate,
        public string $dueDate,
        /** What the crédito line was for. */
        public string $amount,
        /** What is still owed. */
        public string $balance,
    ) {
    }

    public static function of(Receivable $r): self
    {
        return new self(
            $r->id()->toRfc4122(),
            $r->invoiceId()->toRfc4122(),
            $r->invoiceNumber(),
            $r->issueDate()->format('Y-m-d'),
            $r->dueDate()->format('Y-m-d'),
            $r->amount()->toString(),
            $r->balance()->toString(),
        );
    }
}
