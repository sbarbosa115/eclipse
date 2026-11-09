<?php

namespace App\Sales\Domain\Model;

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

#[ORM\Entity]
#[ORM\Table(name: 'quotation_line')]
class QuotationLine extends CommercialLine
{
    /** @var Collection<int, QuotationLineWithholding> */
    #[ORM\OneToMany(targetEntity: QuotationLineWithholding::class, mappedBy: 'line', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $withholdings;

    /**
     * @param list<TaxSnapshot> $withholdings
     */
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
        array $withholdings,
    ) {
        $this->withholdings = new ArrayCollection();
        parent::__construct($companyId, $position, $productId, null, $description, $quantity, $unitPrice, $discount, $chargeTax);
        $this->addWithholdings($withholdings);
    }

    public function document(): Quotation
    {
        return $this->document;
    }

    public function withholdings(): array
    {
        return $this->withholdings->getValues();
    }

    protected function newWithholding(int $position, TaxSnapshot $tax): LineWithholding
    {
        return new QuotationLineWithholding($this, $this->companyId, $position, $tax);
    }

    protected function addWithholding(LineWithholding $withholding): void
    {
        \assert($withholding instanceof QuotationLineWithholding);
        $this->withholdings->add($withholding);
    }
}
