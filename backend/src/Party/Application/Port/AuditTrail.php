<?php

namespace App\Party\Application\Port;

use Symfony\Component\Uid\Uuid;

/** Writes what the audit log must remember about a tercero: personal data exported or erased (Ley 1581). */
interface AuditTrail
{
    /** @param array<string, mixed> $data */
    public function record(Uuid $companyId, Uuid $userId, string $action, Uuid $terceroId, array $data = []): void;
}
