<?php

namespace App\Shared\Application\Company;

use Symfony\Component\Uid\Uuid;

/**
 * Gives a new company what its context needs before anyone uses it: the PUC chart and the posting rules (Ledger),
 * the seeded taxes and payment methods, the numbering series (Company)… Called by the Company context inside the
 * sign-up transaction, in priority order, so a company never exists half set up.
 *
 * A context implements it once; services.yaml tags every implementation (highest priority first, via
 * getPriority()).
 */
interface CompanyProvisioner
{
    public function provision(Uuid $companyId): void;

    /** Higher runs first: the chart (100) before taxes and payment methods that point at its accounts (50). */
    public static function getPriority(): int;
}
