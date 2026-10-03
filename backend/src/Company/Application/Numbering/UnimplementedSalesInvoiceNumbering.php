<?php

namespace App\Company\Application\Numbering;

use Symfony\Component\Uid\Uuid;

/**
 * Placeholder until the "company" item implements resolution numbering. Replace, do not extend.
 */
final class UnimplementedSalesInvoiceNumbering implements SalesInvoiceNumbering
{
    public function next(Uuid $companyId, \DateTimeImmutable $issueDate): AuthorisedNumber
    {
        throw new \LogicException('Sales invoice numbering is built by the "company" item.');
    }
}
