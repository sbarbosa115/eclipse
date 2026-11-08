<?php

namespace App\Ledger\Application\Command;

use Symfony\Component\Uid\Uuid;

/** AddAccount's result: the new account, and what moved to it when it subdivided an account in use. */
final readonly class AccountAdded
{
    public function __construct(
        public Uuid $id,
        /** Null unless it is the first auxiliar of a postable account and the references were moved. */
        public ?AccountMoves $moves = null,
    ) {
    }
}
