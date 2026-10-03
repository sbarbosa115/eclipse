<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Model\LedgerSettings;
use App\Ledger\Domain\Repository\LedgerSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineLedgerSettingsRepository implements LedgerSettingsRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function of(Uuid $companyId): LedgerSettings
    {
        $settings = $this->em->find(LedgerSettings::class, $companyId);
        if (null === $settings) {
            $settings = new LedgerSettings($companyId);
            $this->em->persist($settings);
        }

        return $settings;
    }
}
