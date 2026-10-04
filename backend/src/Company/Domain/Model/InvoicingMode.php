<?php

namespace App\Company\Domain\Model;

enum InvoicingMode: string
{
    case Electronic = 'electronic';
    /** Only after the administrator confirms the DIAN permission (§4.1). */
    case Manual = 'manual';
}
