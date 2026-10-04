<?php

namespace App\Sales\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** A recibo de caja (§4.9): emitted when saved; `send` is *Guardar y enviar por mail*. */
final class CashReceiptInput
{
    #[Assert\NotBlank(message: 'Choose the client.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    /** YYYY-MM-DD. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $receiptDate = '';

    /** *Dónde ingresa el dinero*: an active contado method. */
    #[Assert\NotBlank(message: 'Choose where the money comes in.')]
    #[Assert\Uuid]
    public string $paymentMethodId = '';

    /** Valor recibido: pesos, up to two decimals. */
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,2})?$/', message: 'Write the amount in pesos, with at most two decimals.')]
    #[Assert\NotBlank(message: 'Write the amount in pesos, with at most two decimals.')]
    public string $amount = '';

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<CashReceiptAllocationInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $allocations = [];

    public bool $send = false;
}
