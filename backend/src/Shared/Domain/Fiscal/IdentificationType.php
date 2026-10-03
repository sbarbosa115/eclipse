<?php

namespace App\Shared\Domain\Fiscal;

/**
 * The DIAN's document types (tipo de identificación), for a company and for every tercero. The value is what the
 * API carries; dianCode() is the code electronic invoicing will send (stage 4).
 */
enum IdentificationType: string
{
    case CivilRegistry = 'rc';
    case IdentityCard = 'ti';
    case CitizenshipCard = 'cc';
    case ForeignerRegistrationCard = 'te';
    case ForeignerId = 'ce';
    case Nit = 'nit';
    case Passport = 'pasaporte';
    case ForeignId = 'die';
    case PermitPep = 'pep';
    case ForeignNit = 'nit_extranjero';
    case Nuip = 'nuip';
    case PermitPpt = 'ppt';

    public function dianCode(): string
    {
        return match ($this) {
            self::CivilRegistry => '11',
            self::IdentityCard => '12',
            self::CitizenshipCard => '13',
            self::ForeignerRegistrationCard => '21',
            self::ForeignerId => '22',
            self::Nit => '31',
            self::Passport => '41',
            self::ForeignId => '42',
            self::PermitPep => '47',
            self::ForeignNit => '50',
            self::Nuip => '91',
            self::PermitPpt => '48',
        };
    }

    /** Only a NIT carries a dígito de verificación. */
    public function hasCheckDigit(): bool
    {
        return self::Nit === $this;
    }
}
