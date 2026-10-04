<?php

namespace App\Sales\Domain\Model;

use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Shared\Domain\Model\OpenItem;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a client owes on one crédito line of a sales invoice (cartera de clientes). Opened at emission for the line's
 * amount; a recibo de caja applies amounts to it (and gives them back when the receipt is voided); voided with its
 * invoice.
 */
#[ORM\Entity]
#[ORM\Table(name: 'receivable')]
#[ORM\Index(name: 'receivable_open', columns: ['company_id', 'tercero_id', 'voided', 'due_date'])]
#[ORM\Index(name: 'receivable_invoice', columns: ['company_id', 'invoice_id'])]
class Receivable extends OpenItem
{
    /** A receipt pays this much of it (§4.9). */
    public function apply(Money $amount): void
    {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('An applied amount is positive.');
        }
        if ($this->voided || $amount->isGreaterThan($this->balance)) {
            throw new AllocationExceedsBalance();
        }
        $this->balance = $this->balance->minus($amount);
    }

    /** A voided receipt gives back what it had applied. */
    public function unapply(Money $amount): void
    {
        $balance = $this->balance->plus($amount);
        if (!$amount->isPositive() || $balance->isGreaterThan($this->amount)) {
            throw new \LogicException('A receivable never gets back more than was applied to it.');
        }
        $this->balance = $balance;
    }

    /** The invoice was voided: nothing is owed any more. */
    public function void(): void
    {
        $this->voided = true;
    }
}
