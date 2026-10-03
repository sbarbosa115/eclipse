<?php

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Model\SeriesKind;
use Symfony\Component\Uid\Uuid;

interface NumberingSeriesRepository
{
    /** The series, locked for update until the transaction ends. */
    public function lock(Uuid $companyId, SeriesKind $kind): NumberingSeries;

    /**
     * The company's series, in the order of SeriesKind.
     *
     * @return list<NumberingSeries>
     */
    public function all(Uuid $companyId): array;

    public function add(NumberingSeries $series): void;
}
