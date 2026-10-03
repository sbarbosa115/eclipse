<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The company's books settings: the fecha de bloqueo contable (§4.1, invariant 4: nothing is emitted or voided on or
 * before it; the accountant moves it forward at period close).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_settings')]
class LedgerSettings implements CompanyOwned
{
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
    ) {
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function lockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    /** Whether a document may be emitted or voided with this date. */
    public function isOpen(\DateTimeImmutable $date): bool
    {
        return null === $this->lockedUntil || $date->format('Y-m-d') > $this->lockedUntil->format('Y-m-d');
    }
}
