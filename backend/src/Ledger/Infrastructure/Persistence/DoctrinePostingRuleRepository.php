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

    public function pointingAt(Uuid $companyId, Uuid $accountId): array
    {
        $rules = $this->em->getRepository(PostingRule::class)->findBy(['companyId' => $companyId, 'accountId' => $accountId]);
        $order = array_flip(array_map(static fn (PostingConcept $c) => $c->value, PostingConcept::cases()));
        usort($rules, static fn (PostingRule $a, PostingRule $b) => $order[$a->concept()->value] <=> $order[$b->concept()->value]);

        return $rules;
    }
}
