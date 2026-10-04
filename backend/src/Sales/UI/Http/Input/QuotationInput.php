<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A draft cotización, whole (create and update): an invoice's header and lines, without formas de pago, and its own fields. */
final class QuotationInput
{
    #[Assert\NotBlank(message: 'Choose the client.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    #[Assert\Uuid]
    public ?string $contactId = null;

    /** Responsable de la cotización: a tercero with role empleado. */
    #[Assert\Uuid]
    public ?string $responsibleId = null;

    /** Fecha de elaboración, YYYY-MM-DD. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $issueDate = '';

    /** Fecha de vencimiento of the offer, YYYY-MM-DD; empty: 30 days after the issue date. */
    #[Assert\Date]
    public ?string $expiryDate = null;

    /** Encabezado: plain text (it is escaped wherever it is shown). */
    #[Assert\Length(max: 5000, maxMessage: 'The text is too long (5000 characters at most).')]
    public ?string $header = null;

    /** Condiciones comerciales: plain text. */
    #[Assert\Length(max: 5000, maxMessage: 'The text is too long (5000 characters at most).')]
    public ?string $terms = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<SalesInvoiceLineInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $lines = [];
}
