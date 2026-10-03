<?php

namespace App\Shared\Domain\Fiscal;

/**
 * Responsabilidades fiscales from the RUT that decide withholdings (§4.2). R-99-PN is the default.
 */
enum FiscalResponsibility: string
{
    case LargeTaxpayer = 'O-13';
    case SelfWithholder = 'O-15';
    case VatWithholdingAgent = 'O-23';
    case SimpleRegime = 'O-47';
    case NotApplicable = 'R-99-PN';
}
