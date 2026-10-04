<?php

namespace App\Purchasing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A recibo de pago (§4.11): emitted when saved; `send` is *Guardar y enviar*. */
final class SupplierPaymentInput
{
    #[Assert\NotBlank(message: 'Choose the supplier.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    /** YYYY-MM-DD. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $receiptDate = '';

    /** *De dónde sale el dinero*: an active contado method. */
    #[Assert\NotBlank(message: 'Choose where the money comes from.')]
    #[Assert\Uuid]
    public string $paymentMethodId = '';

    /** Valor pagado: pesos, up to two decimals. */
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    #[Assert\NotBlank(message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<SupplierPaymentAllocationInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $allocations = [];

    public bool $send = false;
}
