<?php

namespace App\Purchasing\Domain\Model;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\PayableVoided;
use App\Shared\Domain\Model\OpenItem;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;

/** What the company owes a supplier on one crédito line of a purchase invoice (cartera de proveedores). */
#[ORM\Entity]
#[ORM\Table(name: 'payable')]
#[ORM\Index(name: 'payable_open', columns: ['company_id', 'tercero_id', 'voided', 'due_date'])]
#[ORM\Index(name: 'payable_invoice', columns: ['company_id', 'invoice_id'])]
class Payable extends OpenItem
{
    /** A supplier payment pays $amount of it (§4.11). */
    public function allocate(Money $amount): void
    {
        $this->assertLive($amount);
        if ($amount->isGreaterThan($this->balance)) {
            throw new AllocationExceedsBalance($amount, $this->balance);
        }
        $this->balance = $this->balance->minus($amount);
    }

    /** A supplier payment that paid $amount of it was voided: it is owed again. */
    public function release(Money $amount): void
    {
        $this->assertLive($amount);
        $paid = $this->amount->minus($this->balance);
        if ($amount->isGreaterThan($paid)) {
            throw new AllocationExceedsBalance($amount, $paid);
        }
        $this->balance = $this->balance->plus($amount);
    }

    /** Its invoice was voided: nothing is owed on it any more. */
    public function void(): void
    {
        $this->voided = true;
    }

    private function assertLive(Money $amount): void
    {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('An allocated amount is positive.');
        }
        if ($this->voided) {
            throw new PayableVoided();
        }
    }
}
