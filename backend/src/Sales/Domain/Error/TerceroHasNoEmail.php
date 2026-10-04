<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** "Emitir y enviar" needs the client's billing e-mail (§4.2 Datos para facturación). */
final class TerceroHasNoEmail extends Refused
{
    public function __construct()
    {
        parent::__construct('tercero_has_no_email', 'The client has no billing e-mail.');
    }
}
