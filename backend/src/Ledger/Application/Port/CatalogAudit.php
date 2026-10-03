<?php

namespace App\Ledger\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Writes a change of the taxes or payment methods catalog to the audit log (Shared AuditLog): who, what, when.
 */
interface CatalogAudit
{
    /**
     * @param string               $action      "tax.created", "payment_method.deactivated"…
     * @param string               $subjectType "tax" or "payment_method"
     * @param array<string, mixed> $data        what changed (from and to), or what was deleted
     */
    public function record(Uuid $companyId, Uuid $userId, string $action, string $subjectType, Uuid $subjectId, array $data): void;
}
