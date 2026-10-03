<?php

namespace App\Party\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class ExportTercero
{
    public function __construct(public Uuid $companyId, public Uuid $userId, public Uuid $terceroId)
    {
    }
}
