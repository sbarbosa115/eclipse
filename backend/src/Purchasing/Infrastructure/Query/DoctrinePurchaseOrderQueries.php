<?php

namespace App\Purchasing\Infrastructure\Query;

use App\Catalog\Application\Query\ProductCatalog;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Purchasing\Application\Query\PurchaseInvoiceLineView;
use App\Purchasing\Application\Query\PurchaseOrderFilter;
use App\Purchasing\Application\Query\PurchaseOrderPage;
use App\Purchasing\Application\Query\PurchaseOrderQueries;
use App\Purchasing\Application\Query\PurchaseOrderSummary;
use App\Purchasing\Application\Query\PurchaseOrderView;
use App\Purchasing\Domain\Model\PurchaseOrder;
use App\Purchasing\Domain\Model\PurchaseOrderLine;
use App\Purchasing\Domain\Model\PurchaseOrderStatus;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Document\LineWithholdingView;
use App\Shared\Domain\Error\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Uid\Uuid;

final class DoctrinePurchaseOrderQueries implements PurchaseOrderQueries
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PurchaseOrderRepository $orders,
        private readonly ProductCatalog $products,
        private readonly LedgerCatalog $ledger,
    ) {
    }

    public function get(Uuid $companyId, Uuid $id): PurchaseOrderView
    {
        $o = $this->orders->get($companyId, $id);

        return new PurchaseOrderView(
            $o->id()->toRfc4122(),
            $o->status()->value,
            $o->number(),
            $o->terceroId()->toRfc4122(),
            $o->terceroName(),
            $o->issueDate()->format('Y-m-d'),
            $o->expectedDate()?->format('Y-m-d'),
            $o->notes(),
            $o->grossTotal()->toString(),
            $o->discountTotal()->toString(),
            $o->subtotal()->toString(),
            $o->taxTotal()->toString(),
            $o->withholdingTotal()->toString(),
            $o->netTotal()->toString(),
            array_map(fn (PurchaseOrderLine $l) => $this->line($companyId, $l), $o->lines()),
            $o->convertedInvoiceId()?->toRfc4122(),
            $o->createdAt()->format(\DATE_ATOM),
            $o->emittedAt()?->format(\DATE_ATOM),
            $o->voidedAt()?->format(\DATE_ATOM),
            $o->voidReason(),
        );
    }

    public function search(Uuid $companyId, PurchaseOrderFilter $filter): PurchaseOrderPage
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $filter->perPage));
        $page = max(1, $filter->page);
        $qb = $this->em->createQueryBuilder()
            ->select('o')->from(PurchaseOrder::class, 'o')
            ->where('o.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->orderBy('o.issueDate', 'DESC')->addOrderBy('o.sequence', 'DESC')->addOrderBy('o.createdAt', 'DESC');
        $query = null === $filter->query ? '' : trim($filter->query);
        if ('' !== $query) {
            $qb->andWhere("o.number LIKE :q ESCAPE '!' OR o.terceroName LIKE :q ESCAPE '!'")
                ->setParameter('q', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%');
        }
        if (null !== $filter->status && null !== PurchaseOrderStatus::tryFrom($filter->status)) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filter->status);
        }
        if (null !== $filter->from) {
            $qb->andWhere('o.issueDate >= :from')->setParameter('from', $filter->from->format('Y-m-d'));
        }
        if (null !== $filter->to) {
            $qb->andWhere('o.issueDate <= :to')->setParameter('to', $filter->to->format('Y-m-d'));
        }
        if (null !== $filter->terceroId && Uuid::isValid($filter->terceroId)) {
            $qb->andWhere('o.terceroId = :tercero')->setParameter('tercero', Uuid::fromString($filter->terceroId), 'uuid');
        }
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery(), false);

        $items = [];
        foreach ($paginator as $o) {
            $items[] = new PurchaseOrderSummary($o->id()->toRfc4122(), $o->status()->value, $o->number(), $o->terceroId()->toRfc4122(), $o->terceroName(), $o->issueDate()->format('Y-m-d'), $o->expectedDate()?->format('Y-m-d'), $o->netTotal()->toString(), $o->convertedInvoiceId()?->toRfc4122());
        }

        return new PurchaseOrderPage($items, \count($paginator), $page, $perPage);
    }

    /** The same shape as a factura de compra's line (the order's lines are read like them). */
    private function line(Uuid $companyId, PurchaseOrderLine $l): PurchaseInvoiceLineView
    {
        $productLabel = $accountLabel = null;
        try {
            if (null !== $l->productId()) {
                $product = $this->products->get($companyId, $l->productId());
                $productLabel = $product->code.' · '.$product->name;
            }
            if (null !== $l->accountId()) {
                $account = $this->ledger->account($companyId, $l->accountId());
                $accountLabel = $account->code.' · '.$account->name;
            }
        } catch (NotFound) {
            // A deleted product or account: the line keeps its description.
        }
        $charge = $l->chargeTax();
        $withholding = $l->withholdingTax();

        return new PurchaseInvoiceLineView(
            $l->id()->toRfc4122(), $l->position(), $l->productId()?->toRfc4122(), $productLabel, $l->accountId()?->toRfc4122(), $accountLabel,
            $l->description(), $l->quantity()->toString(), $l->unitPrice()->toString(), $l->discount()->toString(),
            $charge->taxId()?->toRfc4122(), $charge->name(), $charge->kind(), $charge->value(),
            $withholding->taxId()?->toRfc4122(), $withholding->name(), $withholding->kind(), $withholding->value(),
            $l->grossAmount()->toString(), $l->discountAmount()->toString(), $l->subtotalAmount()->toString(), $l->taxAmount()->toString(), $l->withholdingAmount()->toString(), $l->totalAmount()->toString(),
            $l->productCode(), LineWithholdingView::ofLine($l),
        );
    }
}
