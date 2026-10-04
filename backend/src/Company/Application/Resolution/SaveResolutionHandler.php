<?php

namespace App\Company\Application\Resolution;

use App\Company\Domain\Error\ResolutionExists;
use App\Company\Domain\Error\ResolutionNotFound;
use App\Company\Domain\Model\InvoicingMode;
use App\Company\Domain\Model\InvoicingResolution;
use App\Company\Domain\Repository\CompanyRepository;
use App\Company\Domain\Repository\InvoicingResolutionRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use Symfony\Component\Uid\Uuid;

final class SaveResolutionHandler implements CommandHandler
{
    public function __construct(
        private readonly InvoicingResolutionRepository $resolutions,
        private readonly CompanyRepository $companies,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(SaveResolution $command): Uuid
    {
        $manualConfirmed = $this->companies->get($command->companyId)->hasConfirmedManualInvoicing();
        $mode = InvoicingMode::from($command->mode);
        $from = new \DateTimeImmutable($command->validFrom);
        $to = new \DateTimeImmutable($command->validTo);

        // Locked: an emission numbering at the same moment waits for this edit, never reads half of it.
        $existing = $this->resolutions->lockCurrent($command->companyId);
        if ($command->create) {
            if (null !== $existing) {
                throw new ResolutionExists();
            }
            $resolution = InvoicingResolution::define($command->companyId, $command->resolutionNumber, $command->prefix, $command->rangeFrom, $command->rangeTo, $from, $to, $mode, $manualConfirmed);
            $this->resolutions->add($resolution);
            $this->audit->record($command->companyId, $command->userId, 'resolution.created', 'invoicing_resolution', $resolution->id(), ['to' => self::snapshot($resolution)]);

            return $resolution->id();
        }

        $resolution = $existing ?? throw new ResolutionNotFound();
        $before = self::snapshot($resolution);
        $resolution->revise($command->resolutionNumber, $command->prefix, $command->rangeFrom, $command->rangeTo, $from, $to, $mode, $manualConfirmed);
        $this->audit->record($command->companyId, $command->userId, 'resolution.updated', 'invoicing_resolution', $resolution->id(), ['from' => $before, 'to' => self::snapshot($resolution)]);

        return $resolution->id();
    }

    /** @return array<string, mixed> */
    private static function snapshot(InvoicingResolution $r): array
    {
        return [
            'resolution_number' => $r->resolutionNumber(),
            'prefix' => $r->prefix(),
            'range_from' => $r->rangeFrom(),
            'range_to' => $r->rangeTo(),
            'valid_from' => $r->validFrom()->format('Y-m-d'),
            'valid_to' => $r->validTo()->format('Y-m-d'),
            'mode' => $r->mode()->value,
        ];
    }
}
