<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** What the libro diario is filtered by (§4.13): dates, an account (and its children), a tercero. */
final readonly class JournalFilter
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $accountCode = null,
        public ?Uuid $terceroId = null,
    ) {
    }
}
