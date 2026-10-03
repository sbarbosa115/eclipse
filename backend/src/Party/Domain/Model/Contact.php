<?php

namespace App\Party\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A named person at a tercero, selectable on documents (§4.2 Contactos, §4.6 header).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tercero_contact')]
class Contact implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Tercero::class, inversedBy: 'contacts')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Tercero $tercero,
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 160)]
        private string $name,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $email = null,
        #[ORM\Column(length: 30, nullable: true)]
        private ?string $phone = null,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tercero(): Tercero
    {
        return $this->tercero;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }
}
