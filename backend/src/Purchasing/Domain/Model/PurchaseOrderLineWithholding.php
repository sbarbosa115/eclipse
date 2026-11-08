<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\LineWithholding;
use App\Shared\Domain\Model\TaxSnapshot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A retención of a PurchaseOrderLine (LineWithholding). */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_line_withholding')]
class PurchaseOrderLineWithholding extends LineWithholding
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: PurchaseOrderLine::class, inversedBy: 'withholdings')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PurchaseOrderLine $line,
        Uuid $companyId,
        int $position,
        TaxSnapshot $tax,
    ) {
        parent::__construct($companyId, $position, $tax);
    }

    public function line(): PurchaseOrderLine
    {
        return $this->line;
    }
}
