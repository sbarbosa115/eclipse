<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** The books' settings as Configuración shows them: the posting rules and the fecha de bloqueo. */
interface BookSettingsQueries
{
    /** @return list<PostingRuleView> in the order of PostingConcept (§5) */
    public function postingRules(Uuid $companyId): array;

    /** @throws \App\Ledger\Domain\Error\PostingRuleNotFound */
    public function postingRule(Uuid $companyId, string $concept): PostingRuleView;

    /** The fecha de bloqueo as Y-m-d, or null while the books are open. */
    public function lockedUntil(Uuid $companyId): ?string;
}
