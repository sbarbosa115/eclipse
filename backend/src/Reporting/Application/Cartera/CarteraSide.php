<?php

namespace App\Reporting\Application\Cartera;

/** Which cartera: what clients owe the company (receivables) or what the company owes suppliers (payables). */
enum CarteraSide: string
{
    case Clients = 'clients';
    case Suppliers = 'suppliers';

    /** The PUC account the side's balance lives in (§5 invariant 3). */
    public function accountPrefix(): string
    {
        return self::Clients === $this ? '1305' : '2205';
    }
}
