<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** "Guardar y enviar" needs the supplier's e-mail. */
final class SupplierHasNoEmail extends Refused
{
    public function __construct()
    {
        parent::__construct('tercero_has_no_email', 'The supplier has no e-mail.');
    }
}
