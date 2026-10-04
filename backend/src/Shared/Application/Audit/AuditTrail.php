<?php

namespace App\Shared\Application\Audit;

use Symfony\Component\Uid\Uuid;

/**
 * Writes one change a person made to the audit log (Shared\Domain\Model\AuditLog): who, what, on what, when. Called
 * by a command handler, so the line is saved in the same transaction as the change, or not at all.
 *
 * Every context records through this port; actions are "<subject>.<past participle>" (`user.invited`,
 * `user.role_changed`, `tax.updated`), and `data` says what changed (`{from, to}`), never a secret.
 */
interface AuditTrail
{
    /**
     * @param Uuid|null            $userId      who did it; null for the system (a scheduled job)
     * @param string               $subjectType what was changed: "user", "tax", "company"…
     * @param array<string, mixed> $data        what changed, from and to
     */
    public function record(Uuid $companyId, ?Uuid $userId, string $action, string $subjectType, ?Uuid $subjectId, array $data = []): void;
}
