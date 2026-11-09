<?php

namespace App\Party\Application\Command;

use App\Party\Domain\Error\InvalidTerceroValue;
use App\Party\Domain\Model\TerceroProfile;
use App\Shared\Application\Geo\Municipalities;

/** Checks a Colombian tercero's municipality against DIVIPOLA and says its name, which becomes the tercero's city. */
final class TerceroPlace
{
    public function __construct(private readonly Municipalities $municipalities)
    {
    }

    /**
     * @return string|null the municipality's name; null for a tercero abroad
     *
     * @throws InvalidTerceroValue when the code is not a DIVIPOLA municipality
     */
    public function municipalityName(TerceroProfile $profile): ?string
    {
        if (null === $profile->municipalityCode) {
            return null;
        }

        return ($this->municipalities->find($profile->municipalityCode) ?? throw new InvalidTerceroValue('municipality_code', 'Choose the municipality.'))->name;
    }
}
