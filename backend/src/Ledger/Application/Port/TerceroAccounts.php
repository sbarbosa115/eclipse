<?php

namespace App\Ledger\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * A tercero's own receivable and payable accounts (§4.2 Cuentas contables), which override the clientes and proveedores
 * posting rules for that tercero. Null when the tercero has none (or does not exist).
 */
interface TerceroAccounts
{
    public function receivableAccountId(Uuid $companyId, Uuid $terceroId): ?Uuid;

    public function payableAccountId(Uuid $companyId, Uuid $terceroId): ?Uuid;
}
