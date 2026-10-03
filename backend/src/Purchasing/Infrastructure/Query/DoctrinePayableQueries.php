<?php

namespace App\Purchasing\Infrastructure\Query;

use App\Purchasing\Application\Query\PayableQueries;
use App\Purchasing\Application\Query\PayableView;
use App\Purchasing\Domain\Model\Payable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePayableQueries implements PayableQueries
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function openFor(Uuid $companyId, Uuid $terceroId): array
    {
        /** @var list<Payable> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('p')->from(Payable::class, 'p')
            ->where('p.companyId = :company AND p.terceroId = :tercero AND p.voided = false AND p.balance > 0')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('tercero', $terceroId, 'uuid')
            ->orderBy('p.dueDate', 'ASC')->addOrderBy('p.invoiceNumber', 'ASC')
            ->getQuery()->getResult();

        return array_map(self::view(...), $rows);
    }

    public function ofInvoice(Uuid $companyId, Uuid $invoiceId): array
    {
        return array_map(self::view(...), $this->em->getRepository(Payable::class)->findBy(['companyId' => $companyId, 'invoiceId' => $invoiceId], ['dueDate' => 'ASC']));
    }

    public static function view(Payable $p): PayableView
    {
        return new PayableView($p->id()->toRfc4122(), $p->invoiceId()->toRfc4122(), $p->invoiceNumber(), $p->terceroId()->toRfc4122(), $p->issueDate()->format('Y-m-d'), $p->dueDate()->format('Y-m-d'), $p->amount()->toString(), $p->balance()->toString(), $p->isVoided());
    }
}
