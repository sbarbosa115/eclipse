<?php

namespace App\Company\Application\Query;

use App\Company\Domain\Error\NumberingSeriesNotFound;
use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Repository\NumberingSeriesRepository;
use Symfony\Component\Uid\Uuid;

/** The series the owner may edit (not the journal's). */
final class NumberingSeriesList
{
    public function __construct(private readonly NumberingSeriesRepository $series)
    {
    }

    /** @return list<NumberingSeriesView> */
    public function list(Uuid $companyId): array
    {
        $views = [];
        foreach ($this->series->all($companyId) as $s) {
            if ($s->kind()->isEditable()) {
                $views[] = self::toView($s);
            }
        }

        return $views;
    }

    public function get(Uuid $companyId, string $kind): NumberingSeriesView
    {
        foreach ($this->list($companyId) as $view) {
            if ($view->kind === $kind) {
                return $view;
            }
        }

        throw new NumberingSeriesNotFound();
    }

    private static function toView(NumberingSeries $s): NumberingSeriesView
    {
        return new NumberingSeriesView($s->kind()->value, $s->prefix(), $s->nextNumber());
    }
}
