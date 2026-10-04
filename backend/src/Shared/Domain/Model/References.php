<?php

namespace App\Shared\Domain\Model;

/**
 * Marks a uuid column that holds another record's id, so the schema carries a real foreign key to that table
 * (Shared\Infrastructure\Doctrine\ForeignKeys) without the model naming the other context's class: another context's
 * record is an id, and its table name is all this says.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class References
{
    public function __construct(
        public string $table,
        /** RESTRICT, CASCADE or SET NULL */
        public string $onDelete = 'RESTRICT',
    ) {
    }
}
