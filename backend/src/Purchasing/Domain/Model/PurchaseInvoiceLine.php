<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\CommercialLine;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A purchase line: a catalog item or an expense / cost account chosen directly (§4.10), never both.
 */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_invoice_line')]
class PurchaseInvoiceLine extends CommercialLine
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: PurchaseInvoice::class, inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PurchaseInvoice $document,
        Uuid $companyId,
        int $position,
        ?Uuid $productId,
        ?Uuid $accountId,
        string $description,
        Quantity $quantity,
        UnitPrice $unitPrice,
        Rate $discount,
        TaxSnapshot $chargeTax,
        TaxSnapshot $withholdingTax,
    ) {
        if ((null === $productId) === (null === $accountId)) {
            throw new \InvalidArgumentException('A purchase line has a product or an account, not both and not neither.');
        }
        parent::__construct($companyId, $position, $productId, $accountId, $description, $quantity, $unitPrice, $discount, $chargeTax, $withholdingTax);
    }

    public function document(): PurchaseInvoice
    {
        return $this->document;
    }
}
