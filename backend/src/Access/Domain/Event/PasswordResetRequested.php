<?php

namespace App\Access\Domain\Event;

use Symfony\Component\Uid\Uuid;

/** A password-reset link was created: the e-mail carrying it goes out once it is saved. Never serialized. */
final readonly class PasswordResetRequested
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        #[\SensitiveParameter]
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
