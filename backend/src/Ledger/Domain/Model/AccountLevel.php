<?php

namespace App\Ledger\Domain\Model;

/**
 * The PUC's levels by code length (Decreto 2650, Art. 6): clase 1 digit, grupo 2, cuenta 4, subcuenta 6, and the
 * company's own auxiliares of 8 or more.
 */
enum AccountLevel: string
{
    case ClassLevel = 'class';
    case Group = 'group';
    case Account = 'account';
    case Subaccount = 'subaccount';
    case Auxiliary = 'auxiliary';

    public static function ofCode(string $code): self
    {
        return match (\strlen($code)) {
            1 => self::ClassLevel,
            2 => self::Group,
            4 => self::Account,
            6 => self::Subaccount,
            default => self::Auxiliary,
        };
    }

    /** Entries post to subcuentas and auxiliares only, never to a level that groups others. */
    public function isPostable(): bool
    {
        return self::Subaccount === $this || self::Auxiliary === $this;
    }
}
