<?php

namespace App\Access\Application\Port;

/** The e-mails that carry a one-use link (queued: they leave after the request, through the worker). */
interface AccessMailer
{
    /** @param string $role owner, billing or accountant */
    public function invitation(string $to, string $companyName, string $inviterName, string $role, #[\SensitiveParameter] string $token, \DateTimeImmutable $expiresAt): void;

    public function passwordReset(string $to, string $name, #[\SensitiveParameter] string $token): void;
}
