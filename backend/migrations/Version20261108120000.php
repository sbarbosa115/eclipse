<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The exógena concept table's defaults for the companies that existed before it (observations PRD §7.7); a company
 * signed up later gets them from ProvisionExogenaConcepts. The rows are written out here, not read from the class, so
 * the migration means the same thing whatever the code becomes. A company that already has a table is left alone.
 */
final class Version20261108120000 extends AbstractMigration
{
    /** format, account prefix, DIAN concept: App\Ledger\Application\Exogena\ExogenaConceptDefaults on 2026-11-08 */
    private const DEFAULTS = [
        ['1001', '5110', '5001'],
        ['1001', '5135', '5003'],
        ['1001', '5120', '5004'],
        ['1001', '6205', '5007'],
        ['1001', '1435', '5007'],
        ['1001', '5195', '5016'],
        ['1007', '41', '4001'],
        ['1007', '42', '4002'],
    ];

    public function getDescription(): string
    {
        return 'Seed ledger_exogena_concept with the default concepts for every existing company.';
    }

    public function up(Schema $schema): void
    {
        // One statement, so "has no table yet" is judged once per company, before any default is written.
        $rows = implode(' UNION ALL ', array_fill(0, \count(self::DEFAULTS), 'SELECT ? AS format, ? AS account_prefix, ? AS concept_code'));
        $this->addSql(
            "INSERT INTO ledger_exogena_concept (id, company_id, format, account_prefix, concept_code)
             SELECT UNHEX(REPLACE(UUID(), '-', '')), c.id, d.format, d.account_prefix, d.concept_code
             FROM company c
             CROSS JOIN ($rows) d
             WHERE NOT EXISTS (SELECT 1 FROM ledger_exogena_concept e WHERE e.company_id = c.id)",
            array_merge(...self::DEFAULTS),
        );
    }

    public function down(Schema $schema): void
    {
        // The rows may have been edited since: what is a default and what the accountant chose cannot be told apart.
        $this->throwIrreversibleMigrationException();
    }
}
