<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\Payable;
use Symfony\Component\Uid\Uuid;

/**
 * What a recibo de pago holds while it pays: the payables it allocates to and their purchase invoices, locked for
 * update (SELECT … FOR UPDATE) until the transaction ends and read afresh from the database. A second payment for the
 * same payable waits for the first to commit, then sees the balance it left, so both never pass the balance check;
 * two payments for different payables of one invoice never overwrite each other's paid amount; a void of the invoice
 * (which locks it too) and a payment run one after the other. Rows are locked in id order (payables, then invoices),
 * so two payments never wait for each other in a circle.
 */
interface PayableLocks
{
    /**
     * @param list<Uuid> $ids
     *
     * @return array<string, Payable> by id (RFC 4122); an id the company does not have is left out
     */
    public function lockForPayment(Uuid $companyId, array $ids): array;
}
