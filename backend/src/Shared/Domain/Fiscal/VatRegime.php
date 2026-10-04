<?php

namespace App\Shared\Domain\Fiscal;

/**
 * Régimen de IVA. Stage 1 implements responsable de IVA only (§9 Q2); the others are stored for later.
 */
enum VatRegime: string
{
    case Responsible = 'responsable';
    case NotResponsible = 'no_responsable';
    case Simple = 'simple';
}
