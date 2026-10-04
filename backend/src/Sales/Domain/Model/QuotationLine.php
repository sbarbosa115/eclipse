<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\CommercialLine;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'quotation_line')]
class QuotationLine extends CommercialLine
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Quotation::class, inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Quotation $document,
        Uuid $companyId,
        int $position,
        ?Uuid $productId,
        string $description,
        Quantity $quantity,
        UnitPrice $unitPrice,
        Rate $discount,
        TaxSnapshot $chargeTax,
        TaxSnapshot $withholdingTax,
    ) {
        parent::__construct($companyId, $position, $productId, null, $description, $quantity, $unitPrice, $discount, $chargeTax, $withholdingTax);
    }

    public function document(): Quotation
    {
        return $this->document;
    }
}
