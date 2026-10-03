<?php

namespace App\Tests\Support;

use App\Company\Application\Numbering\Numbering;
use App\Company\Application\Numbering\ResolutionSalesInvoiceNumbering;
use App\Company\Application\Numbering\SalesInvoiceNumbering;
use App\Company\Infrastructure\Persistence\DoctrineInvoicingResolutionRepository;
use App\Company\Infrastructure\Persistence\DoctrineNumberingSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

/** The container does not expose private services to tests: the real numbering is assembled from its real parts. */
trait BuildsNumbering
{
    private static function salesInvoiceNumbering(EntityManagerInterface $em): SalesInvoiceNumbering
    {
        return new ResolutionSalesInvoiceNumbering(new DoctrineInvoicingResolutionRepository($em), new Numbering(new DoctrineNumberingSeriesRepository($em)));
    }
}
