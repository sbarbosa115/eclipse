<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\References;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Adds to the schema Doctrine builds from the mapping the foreign keys the mapping cannot say: every company-owned
 * table's company_id → company(id), and every #[References] column → its table(id). Because they are part of the
 * generated schema, migrations:diff writes them and schema:validate agrees with the database.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class ForeignKeys
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();
        foreach ($args->getEntityManager()->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || !$schema->hasTable($metadata->getTableName())) {
                continue;
            }
            $table = $schema->getTable($metadata->getTableName());
            foreach (self::references($metadata) as $column => $reference) {
                if (!$table->hasColumn($column) || !$schema->hasTable($reference->table)) {
                    continue;
                }
                $name = substr('fk_'.$metadata->getTableName().'_'.$column, 0, 64);
                if ($table->hasForeignKey($name)) {
                    continue;
                }
                $table->addForeignKeyConstraint($reference->table, [$column], ['id'], ['onDelete' => $reference->onDelete], $name);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, References> column name => reference
     */
    private static function references(ClassMetadata $metadata): array
    {
        $references = [];
        $class = $metadata->getReflectionClass();
        if ($class->implementsInterface(CompanyOwned::class) && 'company' !== $metadata->getTableName()) {
            $references['company_id'] = new References('company');
        }
        foreach ($metadata->getFieldNames() as $field) {
            if (str_contains($field, '.') || !$class->hasProperty($field)) {
                continue;
            }
            $attribute = $class->getProperty($field)->getAttributes(References::class)[0] ?? null;
            if (null !== $attribute) {
                $references[$metadata->getColumnName($field)] = $attribute->newInstance();
            }
        }

        return $references;
    }
}
