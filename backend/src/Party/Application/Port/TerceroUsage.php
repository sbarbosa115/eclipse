<?php

namespace App\Party\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * Whether any document (or the books) names a tercero. Every context's tables that carry a `tercero_id` count: a
 * tercero in use is deactivated, never deleted.
 */
interface TerceroUsage
{
    public function isReferenced(Uuid $companyId, Uuid $terceroId): bool;
}
