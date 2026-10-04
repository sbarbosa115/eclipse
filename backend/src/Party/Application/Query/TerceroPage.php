<?php

namespace App\Party\Application\Query;

use App\Party\Domain\Model\Tercero;

final readonly class TerceroPage
{
    /** @param list<Tercero> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage)
    {
    }
}
