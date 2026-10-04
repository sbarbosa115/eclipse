<?php

namespace App\Access\Application\Query;

/** A user as Configuración › Usuarios lists them. */
final readonly class UserListItem
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $role,
        public string $status,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastSignInAt,
        /** Until when the invitation link works (an invitee only). */
        public ?\DateTimeImmutable $invitationExpiresAt,
    ) {
    }
}
