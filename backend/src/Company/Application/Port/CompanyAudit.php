<?php

namespace App\Company\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Writes a change of the company's settings to the audit log (Shared AuditLog): who, what, when.
 */
interface CompanyAudit
{
    /**
     * @param string               $action      "company.updated", "resolution.created", "numbering_series.updated"…
     * @param string               $subjectType "company", "invoicing_resolution" or "numbering_series"
     * @param array<string, mixed> $data        what changed (from and to)
     */
    public function record(Uuid $companyId, Uuid $userId, string $action, string $subjectType, Uuid $subjectId, array $data): void;
}
