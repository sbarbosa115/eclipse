<?php

namespace App\Ledger\Infrastructure\Query;

use App\Ledger\Application\Query\BookSettingsQueries;
use App\Ledger\Application\Query\PostingRuleView;
use App\Ledger\Domain\Error\PostingRuleNotFound;
use App\Ledger\Domain\Model\ConceptAccounts;
use App\Shared\Domain\Accounting\PostingConcept;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class DbalBookSettingsQueries implements BookSettingsQueries
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function postingRules(Uuid $companyId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT r.concept, a.id, a.code, a.name FROM posting_rule r JOIN ledger_account a ON a.id = r.account_id WHERE r.company_id = ?',
            [$companyId->toBinary()],
        );
        $byConcept = array_column($rows, null, 'concept');

        $views = [];
        foreach (PostingConcept::cases() as $concept) {
            if (isset($byConcept[$concept->value])) {
                $views[] = self::view($concept, $byConcept[$concept->value]);
            }
        }

        return $views;
    }

    public function postingRule(Uuid $companyId, string $concept): PostingRuleView
    {
        foreach ($this->postingRules($companyId) as $view) {
            if ($view->concept === $concept) {
                return $view;
            }
        }

        throw new PostingRuleNotFound();
    }

    public function lockedUntil(Uuid $companyId): ?string
    {
        $date = $this->db->fetchOne('SELECT locked_until FROM ledger_settings WHERE company_id = ?', [$companyId->toBinary()]);

        return \is_string($date) ? substr($date, 0, 10) : null;
    }

    /** @param array<string, mixed> $row */
    private static function view(PostingConcept $concept, array $row): PostingRuleView
    {
        return new PostingRuleView($concept->value, Uuid::fromBinary((string) $row['id'])->toRfc4122(), (string) $row['code'], (string) $row['name'], ConceptAccounts::prefixes($concept));
    }
}
