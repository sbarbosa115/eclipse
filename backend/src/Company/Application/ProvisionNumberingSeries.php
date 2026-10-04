<?php

namespace App\Company\Application;

use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Model\SeriesKind;
use App\Company\Domain\Repository\NumberingSeriesRepository;
use App\Shared\Application\Company\CompanyProvisioner;
use Symfony\Component\Uid\Uuid;

/**
 * A new company starts every internal series at 1 with its default prefix (C, RC, FC, RP…).
 */
final class ProvisionNumberingSeries implements CompanyProvisioner
{
    public function __construct(private readonly NumberingSeriesRepository $series)
    {
    }

    public function provision(Uuid $companyId): void
    {
        foreach (SeriesKind::cases() as $kind) {
            $this->series->add(new NumberingSeries($companyId, $kind, $kind->defaultPrefix()));
        }
    }

    public static function getPriority(): int
    {
        return 200;
    }
}
