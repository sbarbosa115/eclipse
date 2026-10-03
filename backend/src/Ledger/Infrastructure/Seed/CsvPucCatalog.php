<?php

namespace App\Ledger\Infrastructure\Seed;

use App\Ledger\Application\Port\PucCatalog;
use App\Ledger\Application\Seed\SeedAccount;
use App\Ledger\Domain\Model\AccountNature;

/**
 * The catálogo from puc.csv (code, name, nature), extracted from the Decreto's PDF: see README.md next to it.
 */
final class CsvPucCatalog implements PucCatalog
{
    /** @var list<SeedAccount>|null */
    private ?array $accounts = null;

    public function accounts(): array
    {
        return $this->accounts ??= self::read(__DIR__.'/puc.csv');
    }

    /** @return list<SeedAccount> */
    private static function read(string $path): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE);
        $accounts = [];
        foreach ($file as $index => $row) {
            if (0 === $index || !\is_array($row) || 3 !== \count($row)) {
                continue;
            }
            [$code, $name, $nature] = array_map(strval(...), $row);
            $accounts[] = new SeedAccount($code, $name, AccountNature::from($nature));
        }
        usort($accounts, static fn (SeedAccount $a, SeedAccount $b) => strcmp($a->code, $b->code));

        return $accounts;
    }
}
