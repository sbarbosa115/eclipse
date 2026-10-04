<?php

namespace App\Ledger\Application\Posting;

enum Side: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
