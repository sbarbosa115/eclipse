<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Error\JournalEntryNotFound;
use App\Ledger\Domain\Model\JournalEntry;
use App\Ledger\Domain\Repository\JournalEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineJournalEntryRepository implements JournalEntryRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $entryId): JournalEntry
    {
        return $this->em->getRepository(JournalEntry::class)->findOneBy(['companyId' => $companyId, 'id' => $entryId]) ?? throw new JournalEntryNotFound();
    }

    public function isReversed(Uuid $companyId, Uuid $entryId): bool
    {
        return null !== $this->em->getRepository(JournalEntry::class)->findOneBy(['companyId' => $companyId, 'reversesId' => $entryId]);
    }

    public function add(JournalEntry $entry): void
    {
        $this->em->persist($entry);
    }
}
