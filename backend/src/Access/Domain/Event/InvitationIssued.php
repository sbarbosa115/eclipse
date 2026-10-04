<?php

namespace App\Access\Domain\Event;

use Symfony\Component\Uid\Uuid;

/**
 * An invitation link was created: the e-mail carrying it goes out once the invitation is saved. Handled in the same
 * process (the command bus), never serialized: it carries the link's secret.
 */
final readonly class InvitationIssued
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $invitedBy,
        #[\SensitiveParameter]
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
