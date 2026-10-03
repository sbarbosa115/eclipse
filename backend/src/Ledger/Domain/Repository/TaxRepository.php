<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Error\TaxNotFound;
use App\Ledger\Domain\Model\Tax;
use Symfony\Component\Uid\Uuid;

interface TaxRepository
{
    /** @throws TaxNotFound another company's id is "not found" too */
    public function get(Uuid $companyId, Uuid $id): Tax;

    public function add(Tax $tax): void;

    public function remove(Tax $tax): void;

    /** Whether the company has a tax with this name (ignoring case and the tax `$except`). */
    public function nameTaken(Uuid $companyId, string $name, ?Uuid $except = null): bool;
}
