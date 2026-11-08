<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** What the person wrote on a draft purchase order: shapes already checked, references not yet (PurchaseOrderWriter). */
final readonly class PurchaseOrderContents
{
    /**
     * @param list<PurchaseLineContents> $lines
     */
    public function __construct(
        public Uuid $terceroId,
        public \DateTimeImmutable $issueDate,
        /** Fecha esperada de entrega. */
        public ?\DateTimeImmutable $expectedDate,
        public ?string $notes,
        public array $lines,
    ) {
    }
}
