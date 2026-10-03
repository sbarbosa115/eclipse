<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Model\PostingRule;
use App\Ledger\Domain\Repository\PostingRuleRepository;
use App\Shared\Domain\Accounting\PostingConcept;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePostingRuleRepository implements PostingRuleRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function forConcept(Uuid $companyId, PostingConcept $concept): ?PostingRule
    {
        return $this->em->getRepository(PostingRule::class)->findOneBy(['companyId' => $companyId, 'concept' => $concept]);
    }

    public function pointsAt(Uuid $companyId, Uuid $accountId): bool
    {
        return null !== $this->em->getRepository(PostingRule::class)->findOneBy(['companyId' => $companyId, 'accountId' => $accountId]);
    }
}
