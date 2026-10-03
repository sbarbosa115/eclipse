<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A forma de pago (§4.5). Contado methods post to their own account (caja, a bank); crédito has none: it posts to the
 * tercero's receivable or payable account (posting rules "clientes" / "proveedores", or the tercero's override).
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment_method')]
#[ORM\Index(name: 'payment_method_company', columns: ['company_id', 'active'])]
class PaymentMethod implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column(length: 8, enumType: PaymentKind::class)]
        private PaymentKind $kind,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        private ?Uuid $accountId,
        #[ORM\Column]
        private bool $standard = false,
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

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): PaymentKind
    {
        return $this->kind;
    }

    public function accountId(): ?Uuid
    {
        return $this->accountId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isStandard(): bool
    {
        return $this->standard;
    }
}
