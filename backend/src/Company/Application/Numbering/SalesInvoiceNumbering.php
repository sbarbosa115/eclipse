<?php

namespace App\Company\Application\Numbering;

use Symfony\Component\Uid\Uuid;

/**
 * The authorised number of a sales invoice, from the company's invoicing resolution (§4.1, §4.8), taken inside the
 * emitting transaction. Refuses with resolution_inactive (outside its dates) or resolution_exhausted (past hasta).
 *
 * Contract fixed by item 0; implemented by the "company" item.
 */
interface SalesInvoiceNumbering
{
    /**
     * @throws \App\Shared\Domain\Error\DomainError resolution_inactive, resolution_exhausted, resolution_missing
     */
    public function next(Uuid $companyId, \DateTimeImmutable $issueDate): AuthorisedNumber;
}
