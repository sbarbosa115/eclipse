<?php

namespace App\Ledger\UI\Http\Output;

final readonly class LockDateOutput
{
    public function __construct(
        /** YYYY-MM-DD, or null while the books are open. */
        public ?string $lockedUntil,
    ) {
    }
}
