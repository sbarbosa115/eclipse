<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** The plan de cuentas as the chart screen browses it. */
interface ChartQueries
{
    /**
     * In code order. $query: digits match a code prefix, words part of the name. $class: '1'…'9'.
     *
     * @return Page<AccountView>
     */
    public function page(Uuid $companyId, string $query, ?string $class, int $page, int $perPage): Page;
}
