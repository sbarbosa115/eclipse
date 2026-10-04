<?php

namespace App\Ledger\Infrastructure\Party;

use App\Ledger\Application\Port\TerceroAccounts;
use App\Party\Application\Query\TerceroDirectory;
use App\Party\Application\Query\TerceroView;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/** The tercero's own accounts, read from the Party context's directory. */
final class DirectoryTerceroAccounts implements TerceroAccounts
{
    public function __construct(private readonly TerceroDirectory $terceros)
    {
    }

    public function receivableAccountId(Uuid $companyId, Uuid $terceroId): ?Uuid
    {
        $id = $this->find($companyId, $terceroId)?->receivableAccountId;

        return null === $id ? null : Uuid::fromString($id);
    }

    public function payableAccountId(Uuid $companyId, Uuid $terceroId): ?Uuid
    {
        $id = $this->find($companyId, $terceroId)?->payableAccountId;

        return null === $id ? null : Uuid::fromString($id);
    }

    private function find(Uuid $companyId, Uuid $terceroId): ?TerceroView
    {
        try {
            return $this->terceros->get($companyId, $terceroId);
        } catch (NotFound) {
            return null;
        }
    }
}
