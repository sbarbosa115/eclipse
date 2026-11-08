<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\LineWithholding;
use App\Shared\Domain\Model\TaxSnapshot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A retención of a QuotationLine (LineWithholding). */
#[ORM\Entity]
#[ORM\Table(name: 'quotation_line_withholding')]
class QuotationLineWithholding extends LineWithholding
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: QuotationLine::class, inversedBy: 'withholdings')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private QuotationLine $line,
        Uuid $companyId,
        int $position,
        TaxSnapshot $tax,
    ) {
        parent::__construct($companyId, $position, $tax);
    }

    public function line(): QuotationLine
    {
        return $this->line;
    }
}
