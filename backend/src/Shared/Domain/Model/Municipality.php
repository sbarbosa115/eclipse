<?php

namespace App\Shared\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * A Colombian municipality from DANE's DIVIPOLA (observations PRD §4.3): 5-digit code, name and department. Public
 * reference data shared by every company, so it is not CompanyOwned and has no company_id: the stated exception to
 * the tenancy rule. Seeded by a migration from src/Shared/Infrastructure/Geo/divipola.csv and read-only after.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'geo_municipality')]
#[ORM\Index(name: 'geo_municipality_department', columns: ['department_code', 'name'])]
class Municipality
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 5, options: ['fixed' => true])]
        private string $code,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 2, options: ['fixed' => true])]
        private string $departmentCode,
        #[ORM\Column(length: 120)]
        private string $departmentName,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function departmentCode(): string
    {
        return $this->departmentCode;
    }

    public function departmentName(): string
    {
        return $this->departmentName;
    }
}
