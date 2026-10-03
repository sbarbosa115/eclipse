<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A draft factura de venta, whole (create and update): header, lines and formas de pago. */
final class SalesInvoiceInput
{
    #[Assert\NotBlank(message: 'Choose the client.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    #[Assert\Uuid]
    public ?string $contactId = null;

    /** Vendedor: a tercero with role empleado. */
    #[Assert\Uuid]
    public ?string $sellerId = null;

    /** Fecha de elaboración, YYYY-MM-DD. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $issueDate = '';

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<SalesInvoiceLineInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $lines = [];

    /** @var list<SalesInvoicePaymentInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 20)]
    public array $payments = [];
}
