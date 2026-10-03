<?php

namespace App\Ledger\Application\Command;

use App\Ledger\Application\Port\AuditTrail;
use App\Ledger\Domain\Repository\LedgerSettingsRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

final class MoveLockDateHandler implements CommandHandler
{
    public function __construct(
        private readonly LedgerSettingsRepository $settings,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(MoveLockDate $command): void
    {
        $settings = $this->settings->of($command->companyId);
        $from = $settings->lockedUntil()?->format('Y-m-d');

        $settings->lockUntil($command->lockedUntil, $this->clock->now());

        $to = $settings->lockedUntil()?->format('Y-m-d');
        if ($from !== $to) {
            $this->audit->record($command->companyId, $command->userId, 'ledger.lock_date_moved', 'ledger_settings', $command->companyId, ['from' => $from, 'to' => $to]);
        }
    }
}
