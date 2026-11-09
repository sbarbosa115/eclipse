<?php

namespace App\Ledger\Application\Exogena;

use Symfony\Component\Uid\Uuid;

/** The owner or the accountant replaces the exógena concept table (observations PRD §4.7 d). */
final readonly class ReplaceExogenaConcepts
{
    /**
     * @param list<ExogenaConceptView> $rows the whole new table
     */
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public array $rows,
    ) {
    }
}
