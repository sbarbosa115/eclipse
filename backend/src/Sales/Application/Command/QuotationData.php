<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** What a draft cotización is written from: an invoice's header and lines (no formas de pago) and its own fields (§4.7). */
final readonly class QuotationData
{
    /**
     * @param list<SalesInvoiceLineData> $lines
     */
    public function __construct(
        public Uuid $terceroId,
        public ?Uuid $contactId,
        /** Responsable de la cotización: a tercero with role empleado. */
        public ?Uuid $responsibleId,
        public \DateTimeImmutable $issueDate,
        /** Fecha de vencimiento of the offer; null: 30 days after the issue date (§9 Q17). */
        public ?\DateTimeImmutable $expiryDate,
        /** Encabezado, plain text. */
        public ?string $header,
        /** Condiciones comerciales, plain text. */
        public ?string $terms,
        public ?string $notes,
        public array $lines,
    ) {
    }
}
