<?php

namespace App\Access\UI\Http\Output;

use App\Access\Application\Query\UserListItem;

/** A user of the company, as the owner manages them (Configuración › Usuarios). */
final readonly class UserOutput
{
    public function __construct(
        public string $id,
        public string $email,
        /** Empty until an invitee accepts and writes it. */
        public string $name,
        /** owner, billing or accountant */
        public string $role,
        /** invited, active or deactivated */
        public string $status,
        /** The person looking at the list. */
        public bool $isYou,
        public string $createdAt,
        public ?string $lastSignInAt,
        /** Until when the invitation link works; null unless invited. */
        public ?string $invitationExpiresAt,
    ) {
    }

    public static function of(UserListItem $u, string $viewerId): self
    {
        return new self(
            $u->id,
            $u->email,
            $u->name,
            $u->role,
            $u->status,
            $u->id === $viewerId,
            $u->createdAt->format(\DATE_ATOM),
            $u->lastSignInAt?->format(\DATE_ATOM),
            $u->invitationExpiresAt?->format(\DATE_ATOM),
        );
    }
}
