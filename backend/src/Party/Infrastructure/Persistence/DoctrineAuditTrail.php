<?php

namespace App\Party\Infrastructure\Persistence;

use App\Party\Application\Port\AuditTrail;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Model\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineAuditTrail implements AuditTrail
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Clock $clock)
    {
    }

    public function record(Uuid $companyId, Uuid $userId, string $action, Uuid $terceroId, array $data = []): void
    {
        $this->em->persist(new AuditLog($companyId, $userId, $action, 'tercero', $terceroId, $data, $this->clock->now()));
    }
}
