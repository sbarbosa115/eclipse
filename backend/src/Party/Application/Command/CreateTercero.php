<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Model\TerceroProfile;
use Symfony\Component\Uid\Uuid;

final readonly class CreateTercero
{
    public function __construct(public Uuid $companyId, public TerceroProfile $profile)
    {
    }
}
