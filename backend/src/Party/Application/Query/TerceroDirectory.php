<?php

namespace App\Party\Application\Query;

use Symfony\Component\Uid\Uuid;

/**
 * What other contexts read about terceros. Scoped to one company: another company's id is "not found".
 */
interface TerceroDirectory
{
    /** @throws \App\Party\Domain\Error\TerceroNotFound */
    public function get(Uuid $companyId, Uuid $terceroId): TerceroView;

    /**
     * A contact of the tercero, or null when it is not one of its contacts.
     */
    public function contactName(Uuid $companyId, Uuid $terceroId, Uuid $contactId): ?string;
}
