<?php

namespace App\Party\Domain\Error;

use App\Shared\Domain\Error\Refused;

/**
 * Tipo + número de identificación (+ código de sucursal) is unique per company. The HTTP layer reports it as a 422
 * with this code and a violation on `identification_number`.
 */
final class DuplicateIdentification extends Refused
{
    public const FIELD = 'identification_number';

    public function __construct()
    {
        parent::__construct('duplicate_identification', 'A tercero with this identification already exists.');
    }
}
