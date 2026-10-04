<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PayableView;

/** A payable the company still owes the supplier (§4.11): factura, fecha, vencimiento, valor, saldo. */
final readonly class OpenPayableOutput
{
    public function __construct(
        /** The payable: what an allocation names. */
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

    public static function of(PayableView $v): self
    {
        return new self($v->id, $v->invoiceId, $v->invoiceNumber, $v->issueDate, $v->dueDate, $v->amount, $v->balance);
    }
}
