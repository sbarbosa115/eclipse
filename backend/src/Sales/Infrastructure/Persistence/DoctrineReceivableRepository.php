<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Domain\Error\ReceivableNotFound;
use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Repository\ReceivableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineReceivableRepository implements ReceivableRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Receivable
    {
        return $this->em->getRepository(Receivable::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new ReceivableNotFound();
    }

    public function add(Receivable $receivable): void
    {
        $this->em->persist($receivable);
    }

    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array
    {
        /** @var list<Receivable> $found */
        $found = $this->em->getRepository(Receivable::class)->findBy(['companyId' => $companyId, 'invoiceId' => $invoiceId], ['dueDate' => 'ASC']);

        return $found;
    }
}
