<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\CommercialLine;
use App\Shared\Domain\Model\LineWithholding;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An orden de compra's line: as a purchase invoice's, a catalog item or an account usable on purchases, never both.
 */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_line')]
class PurchaseOrderLine extends CommercialLine
{
    /** @var Collection<int, PurchaseOrderLineWithholding> */
    #[ORM\OneToMany(targetEntity: PurchaseOrderLineWithholding::class, mappedBy: 'line', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $withholdings;

    /**
     * @param list<TaxSnapshot> $withholdings
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: PurchaseOrder::class, inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PurchaseOrder $document,
        Uuid $companyId,
        int $position,
        ?Uuid $productId,
        ?Uuid $accountId,
        string $description,
        Quantity $quantity,
        UnitPrice $unitPrice,
        Rate $discount,
        TaxSnapshot $chargeTax,
        array $withholdings,
    ) {
        $this->withholdings = new ArrayCollection();
        if ((null === $productId) === (null === $accountId)) {
            throw new \InvalidArgumentException('A purchase line has a product or an account, not both and not neither.');
        }
        parent::__construct($companyId, $position, $productId, $accountId, $description, $quantity, $unitPrice, $discount, $chargeTax);
        $this->addWithholdings($withholdings);
    }

    public function document(): PurchaseOrder
    {
        return $this->document;
    }

    public function withholdings(): array
    {
        return $this->withholdings->getValues();
    }

    protected function newWithholding(int $position, TaxSnapshot $tax): LineWithholding
    {
        return new PurchaseOrderLineWithholding($this, $this->companyId, $position, $tax);
    }

    protected function addWithholding(LineWithholding $withholding): void
    {
        \assert($withholding instanceof PurchaseOrderLineWithholding);
        $this->withholdings->add($withholding);
    }
}
