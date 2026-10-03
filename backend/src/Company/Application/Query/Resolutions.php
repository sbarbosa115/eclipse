<?php

namespace App\Company\Application\Query;

use App\Company\Domain\Model\InvoicingResolution;
use App\Company\Domain\Repository\CompanyRepository;
use App\Company\Domain\Repository\InvoicingResolutionRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

/** The invoicing resolution, its status today and the company's warning thresholds. */
final class Resolutions
{
    /** The resolution's dates are calendar days in Colombia: "today" must not tick over at 7 pm. */
    private const TIMEZONE = 'America/Bogota';

    public function __construct(
        private readonly InvoicingResolutionRepository $resolutions,
        private readonly CompanyRepository $companies,
        private readonly Clock $clock,
    ) {
    }

    public function settings(Uuid $companyId): ResolutionSettingsView
    {
        $company = $this->companies->get($companyId);
        $resolution = $this->resolutions->current($companyId);

        return new ResolutionSettingsView(
            null === $resolution ? null : self::toView($resolution),
            $this->statusOf($resolution, $company->resolutionWarningNumbers(), $company->resolutionWarningDays()),
            $company->manualInvoicingConfirmedAt()?->format(\DATE_ATOM),
        );
    }

    public function status(Uuid $companyId): ResolutionStatusView
    {
        $company = $this->companies->get($companyId);

        return $this->statusOf($this->resolutions->current($companyId), $company->resolutionWarningNumbers(), $company->resolutionWarningDays());
    }

    private function statusOf(?InvoicingResolution $r, int $warnNumbers, int $warnDays): ResolutionStatusView
    {
        if (null === $r) {
            return new ResolutionStatusView('missing', 0, 0, false, $warnNumbers, $warnDays);
        }
        $today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return new ResolutionStatusView($r->status($today)->value, $r->numbersLeft(), $r->daysLeft($today), $r->isRunningOut($today, $warnNumbers, $warnDays), $warnNumbers, $warnDays);
    }

    private static function toView(InvoicingResolution $r): ResolutionView
    {
        return new ResolutionView($r->id()->toRfc4122(), $r->resolutionNumber(), $r->prefix(), $r->rangeFrom(), $r->rangeTo(), $r->validFrom()->format('Y-m-d'), $r->validTo()->format('Y-m-d'), $r->mode()->value, $r->nextNumber(), $r->hasIssuedNumbers());
    }
}
