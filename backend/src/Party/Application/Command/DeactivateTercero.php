<?php

namespace App\Party\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class DeactivateTercero
{
    public function __construct(public Uuid $companyId, public Uuid $terceroId)
    {
    }
}
