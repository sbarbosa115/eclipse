<?php

namespace App\Ledger\Application\Port;

use App\Ledger\Application\Seed\SeedAccount;

/**
 * The official catálogo of the PUC (Decreto 2650 de 1993, Art. 6) down to subcuentas: what every company starts from.
 */
interface PucCatalog
{
    /** @return list<SeedAccount> ordered by code, every parent before its children */
    public function accounts(): array;
}
