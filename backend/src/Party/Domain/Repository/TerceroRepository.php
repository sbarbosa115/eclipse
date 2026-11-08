<?php

namespace App\Party\Domain\Repository;

use App\Party\Domain\Model\Tercero;
use Symfony\Component\Uid\Uuid;

interface TerceroRepository
{
    /** @throws \App\Party\Domain\Error\TerceroNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): Tercero;

    /** The tercero with this identification and branch, if the company has one. */
    public function findByIdentification(Uuid $companyId, string $identificationType, string $identificationNumber, string $branchCode): ?Tercero;

    public function add(Tercero $tercero): void;

    public function remove(Tercero $tercero): void;

    /**
     * The company's terceros whose own receivable or payable account is this one (observations PRD §4.1: they move when it is subdivided).
     *
     * @return list<Tercero>
     */
    public function usingAccount(Uuid $companyId, Uuid $accountId): array;
}
