<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Model\ExogenaConcept;
use App\Ledger\Domain\Repository\ExogenaConceptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineExogenaConceptRepository implements ExogenaConceptRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function all(Uuid $companyId): array
    {
        return $this->em->getRepository(ExogenaConcept::class)->findBy(['companyId' => $companyId], ['format' => 'ASC', 'accountPrefix' => 'ASC']);
    }

    public function add(ExogenaConcept $concept): void
    {
        $this->em->persist($concept);
    }

    public function remove(ExogenaConcept $concept): void
    {
        $this->em->remove($concept);
    }
}
