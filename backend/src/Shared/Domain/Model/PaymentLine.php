<?php

namespace App\Shared\Domain\Model;

use App\Shared\Domain\Money\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A row of an invoice's formas de pago (§4.6): the method as it was (name, kind, account), the amount, and for crédito
 * its due date. Their sum must equal the invoice's total neto to emit.
 */
#[ORM\MappedSuperclass]
abstract class PaymentLine implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    protected Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        protected Uuid $companyId,
        #[ORM\Column]
        protected int $position,
        #[ORM\Column(type: 'uuid')]
        #[References('payment_method')]
        protected Uuid $paymentMethodId,
        #[ORM\Column(length: 80)]
        protected string $methodName,
        #[ORM\Column(length: 8, enumType: PaymentKind::class)]
        protected PaymentKind $kind,
        /** Contado: the method's account. Crédito: null (the tercero's receivable or payable account). */
        #[ORM\Column(type: 'uuid', nullable: true)]
        protected ?Uuid $accountId,
        #[ORM\Column(type: 'money')]
        protected Money $amount,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        protected ?\DateTimeImmutable $dueDate,
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

    public function position(): int
    {
        return $this->position;
    }

    public function paymentMethodId(): Uuid
    {
        return $this->paymentMethodId;
    }

    public function methodName(): string
    {
        return $this->methodName;
    }

    public function kind(): PaymentKind
    {
        return $this->kind;
    }

    public function accountId(): ?Uuid
    {
        return $this->accountId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }
}
