<?php

namespace App\Shared\UI\Http\Security;

use Symfony\Component\Uid\Uuid;

/**
 * Who is signed in, as any context's controller needs them: their company (every command and query is scoped to it)
 * and their id (who created, emitted or voided a document). The Access context's security user implements it.
 */
interface SignedInUser
{
    public function userId(): Uuid;

    public function companyId(): Uuid;

    public function role(): string;
}
