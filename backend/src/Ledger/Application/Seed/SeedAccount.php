<?php

namespace App\Ledger\Application\Seed;

use App\Ledger\Domain\Model\AccountNature;

/** One account of the catálogo as the seed reads it. */
final readonly class SeedAccount
{
    public function __construct(
        public string $code,
        public string $name,
        public AccountNature $nature,
    ) {
    }
}
