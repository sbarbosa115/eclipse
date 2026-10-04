<?php

namespace App\Party\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class ReactivateTercero
{
    public function __construct(public Uuid $companyId, public Uuid $terceroId)
    {
    }
}
