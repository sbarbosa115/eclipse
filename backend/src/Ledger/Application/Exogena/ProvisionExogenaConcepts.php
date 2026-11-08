<?php

namespace App\Ledger\Application\Exogena;

use App\Ledger\Domain\Model\ExogenaConcept;
use App\Ledger\Domain\Repository\ExogenaConceptRepository;
use App\Shared\Application\Company\CompanyProvisioner;
use Symfony\Component\Uid\Uuid;

/** A new company's exógena concept table: the defaults (ExogenaConceptDefaults), editable from day one. */
final class ProvisionExogenaConcepts implements CompanyProvisioner
{
    public function __construct(private readonly ExogenaConceptRepository $concepts)
    {
    }

    public function provision(Uuid $companyId): void
    {
        foreach (ExogenaConceptDefaults::ROWS as [$format, $prefix, $concept]) {
            $this->concepts->add(new ExogenaConcept($companyId, $format, $prefix, $concept));
        }
    }

    public static function getPriority(): int
    {
        return 40;
    }
}
