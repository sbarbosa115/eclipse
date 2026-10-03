<?php

namespace App\Ledger\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Records a change to the books' settings in the audit log (a posting rule, the lock date, an account): who, when,
 * from what to what.
 */
interface AuditTrail
{
    /**
     * @param array<string, mixed> $data
     */
    public function record(Uuid $companyId, Uuid $userId, string $action, string $subjectType, ?Uuid $subjectId, array $data): void;
}
