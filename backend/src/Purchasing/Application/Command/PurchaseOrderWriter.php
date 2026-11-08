<?php

namespace App\Purchasing\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\InvalidPurchaseOrder;
use App\Purchasing\Domain\Model\PurchaseOrder;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/**
 * Turns what a person sent into a draft purchase order's content: the supplier's name copied, the lines resolved with
 * the same rules as a factura de compra's (PurchaseDraftResolver::resolveLines). Every problem is reported at once, by
 * field path.
 */
final class PurchaseOrderWriter
{
    public function __construct(
        private readonly TerceroDirectory $terceros,
        private readonly PurchaseDraftResolver $resolver,
    ) {
    }

    /** A new draft order (not stored yet) holding the contents. */
    public function create(Uuid $companyId, Uuid $userId, PurchaseOrderContents $contents, \DateTimeImmutable $now): PurchaseOrder
    {
        $order = new PurchaseOrder($companyId, $contents->terceroId, '', $contents->issueDate, $userId, $now);
        $this->write($order, $contents);

        return $order;
    }

    /** @throws InvalidPurchaseOrder */
    public function write(PurchaseOrder $order, PurchaseOrderContents $contents): void
    {
        $violations = [];
        $name = $order->terceroName();
        try {
            $supplier = $this->terceros->get($order->companyId(), $contents->terceroId);
            $name = $supplier->displayName;
            if (!$supplier->active && !$contents->terceroId->equals($order->terceroId())) {
                $violations[] = ['field' => 'tercero_id', 'message' => 'This tercero is inactive.'];
            }
        } catch (NotFound) {
            $violations[] = ['field' => 'tercero_id', 'message' => 'This tercero does not exist.'];
        }
        if (null !== $contents->expectedDate && $contents->expectedDate->format('Y-m-d') < $contents->issueDate->format('Y-m-d')) {
            $violations[] = ['field' => 'expected_date', 'message' => 'The expected delivery cannot be before the order date.'];
        }
        [$lines, $lineViolations] = $this->resolver->resolveLines($order->companyId(), $contents->lines, $order->lines(), $contents->issueDate);
        array_push($violations, ...$lineViolations);
        if ([] !== $violations) {
            throw new InvalidPurchaseOrder($violations);
        }

        $order->revise($contents->terceroId, $name, $contents->issueDate, $contents->expectedDate, $contents->notes);
        $order->replaceLines($lines);
    }
}
