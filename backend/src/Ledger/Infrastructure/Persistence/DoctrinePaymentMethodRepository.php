<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Error\PaymentMethodNotFound;
use App\Ledger\Domain\Model\PaymentMethod;
use App\Ledger\Domain\Repository\PaymentMethodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePaymentMethodRepository implements PaymentMethodRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): PaymentMethod
    {
        return $this->em->getRepository(PaymentMethod::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new PaymentMethodNotFound();
    }

    public function add(PaymentMethod $method): void
    {
        $this->em->persist($method);
    }

    public function remove(PaymentMethod $method): void
    {
        $this->em->remove($method);
    }

    public function nameTaken(Uuid $companyId, string $name, ?Uuid $except = null): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')->from(PaymentMethod::class, 'm')
            ->where('m.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->andWhere('LOWER(m.name) = :name')->setParameter('name', mb_strtolower(trim($name)));
        if (null !== $except) {
            $qb->andWhere('m.id <> :except')->setParameter('except', $except, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
