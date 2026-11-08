<?php

namespace App\Ledger\Application\Exogena;

use App\Ledger\Domain\Model\ExogenaConcept;
use App\Ledger\Domain\Repository\ExogenaConceptRepository;
use Symfony\Component\Uid\Uuid;

/** The company's exógena concept table as Configuración and the exógena working papers (Reporting) read it. */
final class ExogenaConcepts
{
    public function __construct(private readonly ExogenaConceptRepository $concepts)
    {
    }

    /** @return list<ExogenaConceptView> by format, then prefix */
    public function table(Uuid $companyId): array
    {
        $rows = array_map(static fn (ExogenaConcept $c) => new ExogenaConceptView($c->format(), $c->accountPrefix(), $c->conceptCode()), $this->concepts->all($companyId));
        // By code as text: "41" before "4135" before "42", whatever the database's collation does with them.
        usort($rows, static fn (ExogenaConceptView $a, ExogenaConceptView $b) => [$a->format, $a->accountPrefix] <=> [$b->format, $b->accountPrefix]);

        return $rows;
    }
}
