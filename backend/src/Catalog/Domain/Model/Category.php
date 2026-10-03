<?php

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A flat product category (§9 Q20). */
#[ORM\Entity]
#[ORM\Table(name: 'product_category')]
#[ORM\UniqueConstraint(name: 'product_category_name', columns: ['company_id', 'name'])]
class Category implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 100)]
        private string $name,
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
}
