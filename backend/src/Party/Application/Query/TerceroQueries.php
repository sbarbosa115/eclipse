<?php

namespace App\Party\Application\Query;

use App\Party\Domain\Model\Tercero;
use Symfony\Component\Uid\Uuid;

/** The read side of the terceros screens. Every method is scoped to one company. */
interface TerceroQueries
{
    /**
     * @param string|null $q      part of the name, trade name or identification (`%` and `_` match literally)
     * @param string|null $role   cliente, proveedor, empleado or otro
     * @param bool|null   $active null for both
     */
    public function search(Uuid $companyId, ?string $q, ?string $role, ?bool $active, int $page, int $perPage): TerceroPage;

    /** @throws \App\Party\Domain\Error\TerceroNotFound */
    public function get(Uuid $companyId, Uuid $terceroId): Tercero;
}
