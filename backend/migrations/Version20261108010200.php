<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** DANE's DIVIPOLA (departamentos and municipios), the reference data of geo_municipality (observations PRD §7.3). */
final class Version20261108010200 extends AbstractMigration
{
    private const SOURCE = __DIR__.'/../src/Shared/Infrastructure/Geo/divipola.csv';

    public function getDescription(): string
    {
        return 'Seed geo_municipality from DIVIPOLA (1 122 municipios, 33 departamentos).';
    }

    public function up(Schema $schema): void
    {
        $file = new \SplFileObject(self::SOURCE);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD);
        $rows = [];
        foreach ($file as $i => $row) {
            if (0 === $i || !\is_array($row) || 4 !== \count($row)) {
                continue;
            }
            $rows[] = \sprintf('(%s, %s, %s, %s)', ...array_map(fn ($v) => $this->connection->quote((string) $v), $row));
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            $this->addSql('INSERT INTO geo_municipality (code, name, department_code, department_name) VALUES '.implode(', ', $chunk));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM geo_municipality');
    }
}
