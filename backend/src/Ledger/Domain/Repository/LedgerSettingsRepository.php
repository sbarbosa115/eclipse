<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\LedgerSettings;
use Symfony\Component\Uid\Uuid;

interface LedgerSettingsRepository
{
    /** The company's settings; a company provisioned without them gets open books. */
    public function of(Uuid $companyId): LedgerSettings;
}
