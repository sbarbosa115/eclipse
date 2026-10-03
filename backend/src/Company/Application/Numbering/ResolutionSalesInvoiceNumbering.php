<?php

namespace App\Company\Application\Numbering;

use App\Company\Domain\Error\ResolutionMissing;
use App\Company\Domain\Repository\InvoicingResolutionRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Numbers a sales invoice from the company's invoicing resolution (§4.1, §4.8). The resolution's row is locked until
 * the emitting transaction ends, so two emissions never share a number, and a rollback gives the number back. Both
 * the authorised number and the internal consecutive come out of the same transaction: an emission that fails after
 * this call loses neither.
 */
final class ResolutionSalesInvoiceNumbering implements SalesInvoiceNumbering
{
    public function __construct(
        private readonly InvoicingResolutionRepository $resolutions,
        private readonly Numbering $numbering,
    ) {
    }

    public function next(Uuid $companyId, \DateTimeImmutable $issueDate): AuthorisedNumber
    {
        $resolution = $this->resolutions->lockCurrent($companyId) ?? throw new ResolutionMissing();
        $authorised = new DocumentNumber($resolution->prefix(), $resolution->take($issueDate));

        return new AuthorisedNumber($resolution->id(), $authorised, $this->numbering->salesInvoiceInternal($companyId));
    }
}
