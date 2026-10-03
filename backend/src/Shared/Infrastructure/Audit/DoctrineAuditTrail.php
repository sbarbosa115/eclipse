<?php

namespace App\Shared\Infrastructure\Audit;

use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Model\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** The audit log as rows of `audit_log`, flushed with the command that made the change. */
final class DoctrineAuditTrail implements AuditTrail
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
    ) {
    }

    public function record(Uuid $companyId, ?Uuid $userId, string $action, string $subjectType, ?Uuid $subjectId, array $data = []): void
    {
        $this->em->persist(new AuditLog($companyId, $userId, $action, $subjectType, $subjectId, $data, $this->clock->now()));
    }
}
