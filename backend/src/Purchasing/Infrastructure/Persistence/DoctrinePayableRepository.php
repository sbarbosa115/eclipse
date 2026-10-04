<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Domain\Error\PayableNotFound;
use App\Purchasing\Domain\Model\Payable;
use App\Purchasing\Domain\Repository\PayableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePayableRepository implements PayableRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Payable
    {
        return $this->em->getRepository(Payable::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new PayableNotFound();
    }

    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array
    {
        return $this->em->getRepository(Payable::class)->findBy(['companyId' => $companyId, 'invoiceId' => $invoiceId], ['dueDate' => 'ASC']);
    }

    public function add(Payable $payable): void
    {
        $this->em->persist($payable);
    }
}
