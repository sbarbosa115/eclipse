<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Part of a receipt or payment applied to one open item (§4.9, §4.11). A receipt's allocations sum to its amount.
 */
#[ORM\MappedSuperclass]
abstract class Allocation implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    protected Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        protected Uuid $companyId,
        #[ORM\Column(type: 'uuid')]
        protected Uuid $openItemId,
        #[ORM\Column(type: 'uuid')]
        protected Uuid $invoiceId,
        #[ORM\Column(length: 40)]
        protected string $invoiceNumber,
        #[ORM\Column(type: 'money')]
        protected Money $amount,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function openItemId(): Uuid
    {
        return $this->openItemId;
    }

    public function invoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function amount(): Money
    {
        return $this->amount;
    }
}
