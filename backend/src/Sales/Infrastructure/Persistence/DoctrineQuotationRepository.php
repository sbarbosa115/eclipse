<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Domain\Error\QuotationNotFound;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Repository\QuotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineQuotationRepository implements QuotationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Quotation
    {
        return $this->em->getRepository(Quotation::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new QuotationNotFound();
    }

    public function add(Quotation $quotation): void
    {
        $this->em->persist($quotation);
    }
}
