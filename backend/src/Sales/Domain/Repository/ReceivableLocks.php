<?php

namespace App\Sales\Domain\Repository;

use App\Sales\Domain\Model\Receivable;
use Symfony\Component\Uid\Uuid;

/**
 * What a recibo de caja holds while it collects: the receivables it allocates to and their invoices, locked for update
 * (SELECT … FOR UPDATE) until the transaction ends and read afresh from the database. A second receipt for the same
 * receivable waits for the first to commit, then sees the balance it left, so both never pass the balance check; two
 * receipts for different receivables of one invoice never overwrite each other's paid amount. Rows are locked in id
 * order, so two receipts never wait for each other in a circle.
 */
interface ReceivableLocks
{
    /**
     * @param list<Uuid> $ids
     *
     * @return array<string, Receivable> by id (RFC 4122); an id the company does not have is left out
     */
    public function lockForCollection(Uuid $companyId, array $ids): array;
}
