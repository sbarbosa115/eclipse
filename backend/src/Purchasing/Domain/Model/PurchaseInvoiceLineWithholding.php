<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\LineWithholding;
use App\Shared\Domain\Model\TaxSnapshot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A retención of a PurchaseInvoiceLine (LineWithholding). */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_invoice_line_withholding')]
class PurchaseInvoiceLineWithholding extends LineWithholding
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: PurchaseInvoiceLine::class, inversedBy: 'withholdings')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PurchaseInvoiceLine $line,
        Uuid $companyId,
        int $position,
        TaxSnapshot $tax,
    ) {
        parent::__construct($companyId, $position, $tax);
    }

    public function line(): PurchaseInvoiceLine
    {
        return $this->line;
    }
}
