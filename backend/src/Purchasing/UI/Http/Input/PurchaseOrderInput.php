<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A draft orden de compra (create and update): supplier, dates, lines as a purchase invoice's, no formas de pago. */
final class PurchaseOrderInput
{
    #[Assert\NotBlank(message: 'Choose the supplier.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $issueDate = '';

    /** Fecha esperada de entrega. */
    #[Assert\Date]
    public ?string $expectedDate = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<PurchaseLineInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $lines = [];
}
