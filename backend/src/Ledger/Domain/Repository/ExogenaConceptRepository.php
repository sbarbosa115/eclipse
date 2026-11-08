<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\ExogenaConcept;
use Symfony\Component\Uid\Uuid;

interface ExogenaConceptRepository
{
    /** @return list<ExogenaConcept> the company's list, by format then prefix */
    public function all(Uuid $companyId): array;

    public function add(ExogenaConcept $concept): void;

    public function remove(ExogenaConcept $concept): void;
}
