<?php

namespace App\Shared\Application\Geo;

/** DANE's DIVIPOLA: departments and municipalities, the reference every context reads (observations PRD §4.3). */
interface Municipalities
{
    /** @return list<DepartmentView> by code */
    public function departments(): array;

    /**
     * Municipalities whose name contains $query (accents and case aside) or whose code starts with it, optionally
     * within one department; by department, then name.
     *
     * @return list<MunicipalityView>
     */
    public function search(string $query, ?string $departmentCode = null, int $limit = 50): array;

    public function find(string $code): ?MunicipalityView;
}
