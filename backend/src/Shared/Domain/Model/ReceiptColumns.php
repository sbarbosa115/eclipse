<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The header of a recibo de caja or de pago (§4.9, §4.11): its number, tercero, date, the contado method the money
 * enters or leaves by (its account copied), the amount, notes, and the entries it posted.
 */
trait ReceiptColumns
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $companyId;

    #[ORM\Column(length: 16, enumType: ReceiptStatus::class)]
    private ReceiptStatus $status = ReceiptStatus::Emitted;

    #[ORM\Column(length: 10)]
    private string $prefix;

    #[ORM\Column]
    private int $sequence;

    #[ORM\Column(length: 40)]
    private string $number;

    #[ORM\Column(type: 'uuid')]
    #[References('tercero')]
    private Uuid $terceroId;

    #[ORM\Column(length: 200)]
    private string $terceroName;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $receiptDate;

    #[ORM\Column(type: 'uuid')]
    #[References('payment_method')]
    private Uuid $paymentMethodId;

    #[ORM\Column(length: 80)]
    private string $methodName;

    #[ORM\Column(type: 'uuid')]
    private Uuid $accountId;

    #[ORM\Column(type: 'money')]
    private Money $amount;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $journalEntryId = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $reversalEntryId = null;

    private function initReceipt(
        Uuid $companyId,
        string $prefix,
        int $sequence,
        Uuid $terceroId,
        string $terceroName,
        \DateTimeImmutable $receiptDate,
        Uuid $paymentMethodId,
        string $methodName,
        Uuid $accountId,
        Money $amount,
        ?string $notes,
    ): void {
        $this->id = Uuid::v7();
        $this->companyId = $companyId;
        $this->prefix = $prefix;
        $this->sequence = $sequence;
        $this->number = '' === $prefix ? (string) $sequence : $prefix.'-'.$sequence;
        $this->terceroId = $terceroId;
        $this->terceroName = $terceroName;
        $this->receiptDate = $receiptDate;
        $this->paymentMethodId = $paymentMethodId;
        $this->methodName = $methodName;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->notes = $notes;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function status(): ReceiptStatus
    {
        return $this->status;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function terceroId(): Uuid
    {
        return $this->terceroId;
    }

    public function terceroName(): string
    {
        return $this->terceroName;
    }

    public function receiptDate(): \DateTimeImmutable
    {
        return $this->receiptDate;
    }

    public function paymentMethodId(): Uuid
    {
        return $this->paymentMethodId;
    }

    public function methodName(): string
    {
        return $this->methodName;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function journalEntryId(): ?Uuid
    {
        return $this->journalEntryId;
    }

    public function reversalEntryId(): ?Uuid
    {
        return $this->reversalEntryId;
    }
}
