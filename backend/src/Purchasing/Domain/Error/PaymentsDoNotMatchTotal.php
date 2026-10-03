<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Money\Money;

/** §4.6: Total formas de pago must equal Total neto to emit. */
final class PaymentsDoNotMatchTotal extends Refused
{
    public function __construct(private readonly Money $paymentsTotal, private readonly Money $netTotal)
    {
        parent::__construct('payments_do_not_match_total', \sprintf('The payments add up to %s, the total is %s.', $paymentsTotal, $netTotal));
    }

    public function details(): array
    {
        return ['payments_total' => $this->paymentsTotal->toString(), 'net_total' => $this->netTotal->toString()];
    }
}
