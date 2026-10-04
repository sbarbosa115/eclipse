<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** What a draft factura de venta is written from: the header, the lines and the formas de pago (§4.6, §4.8). */
final readonly class SalesInvoiceData
{
    /**
     * @param list<SalesInvoiceLineData>    $lines
     * @param list<SalesInvoicePaymentData> $payments
     */
    public function __construct(
        public Uuid $terceroId,
        public ?Uuid $contactId,
        /** Vendedor: a tercero with role empleado (optional, §4.8). */
        public ?Uuid $sellerId,
        public \DateTimeImmutable $issueDate,
        public ?string $notes,
        public array $lines,
        public array $payments,
    ) {
    }
}
