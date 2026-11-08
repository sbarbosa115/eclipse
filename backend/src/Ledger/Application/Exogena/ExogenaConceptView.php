<?php

namespace App\Ledger\Application\Exogena;

/** One row of the exógena concept table: accounts starting with $accountPrefix report under $conceptCode in $format. */
final readonly class ExogenaConceptView
{
    public function __construct(
        public string $format,
        public string $accountPrefix,
        public string $conceptCode,
    ) {
    }

    /**
     * The concept an account reports under in a format: the row with the longest prefix of its code, or null when no row
     * covers it.
     *
     * @param list<self> $table
     */
    public static function conceptFor(array $table, string $format, string $accountCode): ?string
    {
        $best = null;
        foreach ($table as $row) {
            if ($row->format === $format && str_starts_with($accountCode, $row->accountPrefix)
                && (null === $best || \strlen($row->accountPrefix) > \strlen($best->accountPrefix))) {
                $best = $row;
            }
        }

        return $best?->conceptCode;
    }
}
