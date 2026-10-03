<?php

namespace App\Purchasing\Infrastructure\Query;

use App\Catalog\Application\Query\ProductCatalog;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Application\Query\PayableQueries;
use App\Purchasing\Application\Query\PurchaseInvoiceFilter;
use App\Purchasing\Application\Query\PurchaseInvoiceLineView;
use App\Purchasing\Application\Query\PurchaseInvoicePage;
use App\Purchasing\Application\Query\PurchaseInvoicePaymentView;
use App\Purchasing\Application\Query\PurchaseInvoiceQueries;
use App\Purchasing\Application\Query\PurchaseInvoiceSummary;
use App\Purchasing\Application\Query\PurchaseInvoiceView;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Model\PurchaseInvoiceLine;
use App\Purchasing\Domain\Model\PurchaseInvoicePayment;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Uid\Uuid;

final class DoctrinePurchaseInvoiceQueries implements PurchaseInvoiceQueries
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly PayableQueries $payables,
        private readonly SupplierFiles $files,
        private readonly ProductCatalog $products,
        private readonly LedgerCatalog $ledger,
    ) {
    }

    public function get(Uuid $companyId, Uuid $id): PurchaseInvoiceView
    {
        $i = $this->invoices->get($companyId, $id);

        return new PurchaseInvoiceView(
            $i->id()->toRfc4122(),
            $i->status()->value,
            $i->number(),
            $i->terceroId()->toRfc4122(),
            $i->terceroName(),
            $i->supplierInvoiceNumber(),
            $i->issueDate()->format('Y-m-d'),
            $i->dueDate()?->format('Y-m-d'),
            $i->notes(),
            $i->grossTotal()->toString(),
            $i->discountTotal()->toString(),
            $i->subtotal()->toString(),
            $i->taxTotal()->toString(),
            $i->withholdingTotal()->toString(),
            $i->netTotal()->toString(),
            $i->paidAmount()->toString(),
            self::balance($i),
            array_map(fn (PurchaseInvoiceLine $l) => $this->line($companyId, $l), $i->lines()),
            array_map(static fn (PurchaseInvoicePayment $p) => new PurchaseInvoicePaymentView($p->id()->toRfc4122(), $p->position(), $p->paymentMethodId()->toRfc4122(), $p->methodName(), $p->kind()->value, $p->amount()->toString(), $p->dueDate()?->format('Y-m-d')), $i->payments()),
            $this->payables->ofInvoice($companyId, $i->id()),
            $this->files->list($companyId, $i->id()),
            $i->journalEntryId()?->toRfc4122(),
            $i->reversalEntryId()?->toRfc4122(),
            $i->createdAt()->format(\DATE_ATOM),
            $i->emittedAt()?->format(\DATE_ATOM),
            $i->voidedAt()?->format(\DATE_ATOM),
            $i->voidReason(),
        );
    }

    public function search(Uuid $companyId, PurchaseInvoiceFilter $filter): PurchaseInvoicePage
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $filter->perPage));
        $page = max(1, $filter->page);
        $qb = $this->em->createQueryBuilder()
            ->select('i')->from(PurchaseInvoice::class, 'i')
            ->where('i.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->orderBy('i.issueDate', 'DESC')->addOrderBy('i.sequence', 'DESC')->addOrderBy('i.createdAt', 'DESC');
        $query = null === $filter->query ? '' : trim($filter->query);
        if ('' !== $query) {
            $qb->andWhere("i.number LIKE :q ESCAPE '!' OR i.supplierInvoiceNumber LIKE :q ESCAPE '!' OR i.terceroName LIKE :q ESCAPE '!'")
                ->setParameter('q', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%');
        }
        if (null !== $filter->status && null !== InvoiceStatus::tryFrom($filter->status)) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filter->status);
        }
        if (null !== $filter->from) {
            $qb->andWhere('i.issueDate >= :from')->setParameter('from', $filter->from->format('Y-m-d'));
        }
        if (null !== $filter->to) {
            $qb->andWhere('i.issueDate <= :to')->setParameter('to', $filter->to->format('Y-m-d'));
        }
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery(), false);

        $items = [];
        foreach ($paginator as $i) {
            $items[] = new PurchaseInvoiceSummary($i->id()->toRfc4122(), $i->status()->value, $i->number(), $i->terceroId()->toRfc4122(), $i->terceroName(), $i->supplierInvoiceNumber(), $i->issueDate()->format('Y-m-d'), $i->dueDate()?->format('Y-m-d'), $i->netTotal()->toString(), $i->paidAmount()->toString(), self::balance($i));
        }

        return new PurchaseInvoicePage($items, \count($paginator), $page, $perPage);
    }

    /** What is still owed: nothing on a voided invoice. */
    private static function balance(PurchaseInvoice $i): string
    {
        return InvoiceStatus::Voided === $i->status() ? '0.00' : $i->netTotal()->minus($i->paidAmount())->toString();
    }

    private function line(Uuid $companyId, PurchaseInvoiceLine $l): PurchaseInvoiceLineView
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
        );
    }
}
