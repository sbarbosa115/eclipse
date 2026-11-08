<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\LineWithholding;
use App\Shared\Domain\Model\TaxSnapshot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A retención of a SalesInvoiceLine (LineWithholding). */
#[ORM\Entity]
#[ORM\Table(name: 'sales_invoice_line_withholding')]
class SalesInvoiceLineWithholding extends LineWithholding
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: SalesInvoiceLine::class, inversedBy: 'withholdings')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SalesInvoiceLine $line,
        Uuid $companyId,
        int $position,
        TaxSnapshot $tax,
    ) {
        parent::__construct($companyId, $position, $tax);
    }

    public function line(): SalesInvoiceLine
    {
        return $this->line;
    }
}
