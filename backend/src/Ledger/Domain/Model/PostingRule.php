<?php

namespace App\Ledger\Domain\Model;

use App\Ledger\Domain\Error\AccountNotAllowedForConcept;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Which account a concept posts to, for one company (§5). Seeded with the defaults; the accountant changes them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'posting_rule')]
#[ORM\UniqueConstraint(name: 'posting_rule_concept', columns: ['company_id', 'concept'])]
class PostingRule implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 40, enumType: PostingConcept::class)]
        private PostingConcept $concept,
        #[ORM\Column(type: 'uuid')]
        #[References('ledger_account')]
        private Uuid $accountId,
    ) {
        $this->id = Uuid::v7();
    }

    /**
     * Points the concept at another account: an active, postable one within the concept's part of the PUC.
     *
     * @throws AccountNotPostable
     * @throws AccountNotAllowedForConcept
     */
    public function pointTo(Account $account): void
    {
        if (!$account->isPostable()) {
            throw new AccountNotPostable($account->code());
        }
        if (!ConceptAccounts::allows($this->concept, $account->code())) {
            throw new AccountNotAllowedForConcept();
        }
        $this->accountId = $account->id();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function concept(): PostingConcept
    {
        return $this->concept;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }
}
