<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * A new draft factura de venta → its id. The quotation item converts a cotización by dispatching it with the
 * quotation's id, which the invoice keeps as its origin (§4.7).
 */
final readonly class CreateDraftSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public SalesInvoiceData $data,
        public ?Uuid $quotationId = null,
    ) {
    }
}
