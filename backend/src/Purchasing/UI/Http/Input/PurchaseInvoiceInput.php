<?php

namespace App\Purchasing\UI\Http\Input;

use App\Purchasing\Application\Command\PurchaseInvoiceContents;
use App\Purchasing\Application\Command\PurchaseLineContents;
use App\Purchasing\Application\Command\PurchasePaymentContents;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** A draft factura de compra (create and update): the header, the lines and the formas de pago. */
final class PurchaseInvoiceInput
{
    #[Assert\NotBlank(message: 'Choose the supplier.')]
    #[Assert\Uuid]
    public string $terceroId = '';

    /** The supplier's own number; a draft may wait for it. */
    #[Assert\Length(max: 40)]
    public ?string $supplierInvoiceNumber = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $issueDate = '';

    #[Assert\Date]
    public ?string $dueDate = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /** @var list<PurchaseLineInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 200)]
    public array $lines = [];

    /** @var list<PurchasePaymentInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 20)]
    public array $payments = [];

    public function toContents(): PurchaseInvoiceContents
    {
        return new PurchaseInvoiceContents(
            Uuid::fromString($this->terceroId),
            $this->supplierInvoiceNumber,
            new \DateTimeImmutable($this->issueDate),
            self::date($this->dueDate),
            $this->notes,
            array_map(static fn (PurchaseLineInput $l) => new PurchaseLineContents(self::uuid($l->productId), self::uuid($l->accountId), $l->description, $l->quantity, $l->unitPrice, $l->discount ?? '0', self::uuid($l->chargeTaxId), self::uuid($l->withholdingTaxId)), array_values($this->lines)),
            array_map(static fn (PurchasePaymentInput $p) => new PurchasePaymentContents(Uuid::fromString($p->paymentMethodId), $p->amount, self::date($p->dueDate)), array_values($this->payments)),
        );
    }

    private static function uuid(?string $id): ?Uuid
    {
        return null === $id || '' === $id ? null : Uuid::fromString($id);
    }

    private static function date(?string $date): ?\DateTimeImmutable
    {
        return null === $date || '' === $date ? null : new \DateTimeImmutable($date);
    }
}
