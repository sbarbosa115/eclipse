<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Money\Money;

/** §4.6: Total formas de pago must equal Total neto to emit. */
final class PaymentsDoNotMatchTotal extends Refused
{
    public function __construct(private readonly Money $payments, private readonly Money $net)
    {
        parent::__construct('payments_do_not_match_total', \sprintf('The payments add up to %s, the net total is %s.', $payments, $net));
    }

    public function details(): array
    {
        return ['payments_total' => $this->payments->toString(), 'net_total' => $this->net->toString()];
    }
}
