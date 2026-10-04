<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * What a tercero still owes (a receivable) or is owed (a payable): one per crédito payment line of an emitted
 * invoice, with its due date and the balance left after allocations (cartera, §4.13).
 */
#[ORM\MappedSuperclass]
abstract class OpenItem implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    protected Uuid $id;

    #[ORM\Column(type: 'money')]
    protected Money $balance;

    /** Voided with its invoice: no longer owed. */
    #[ORM\Column]
    protected bool $voided = false;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        protected Uuid $companyId,
        #[ORM\Column(type: 'uuid')]
        protected Uuid $invoiceId,
        #[ORM\Column(length: 40)]
        protected string $invoiceNumber,
        #[ORM\Column(type: 'uuid')]
        #[References('tercero')]
        protected Uuid $terceroId,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        protected \DateTimeImmutable $issueDate,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        protected \DateTimeImmutable $dueDate,
        #[ORM\Column(type: 'money')]
        protected Money $amount,
    ) {
        $this->id = Uuid::v7();
        $this->balance = $amount;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function invoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function terceroId(): Uuid
    {
        return $this->terceroId;
    }

    public function issueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function dueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function isVoided(): bool
    {
        return $this->voided;
    }

    public function isOpen(): bool
    {
        return !$this->voided && $this->balance->isPositive();
    }
}
