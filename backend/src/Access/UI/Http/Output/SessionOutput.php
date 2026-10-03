<?php

namespace App\Access\UI\Http\Output;

/**
 * Who is signed in and to which company: what the app shell shows and what the UI decides menus by.
 */
final readonly class SessionOutput
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $name,
        /** owner, billing or accountant */
        public string $role,
        public string $companyId,
        public string $companyName,
        public string $companyNit,
        public ?string $companyCheckDigit,
    ) {
    }
}
