<?php

namespace App\Purchasing\UI\Http\Input;

use App\Purchasing\Application\Command\PurchaseLineContents;
use App\Shared\UI\Http\Input\LineWithholdingInput;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** A line: a product or an expense account (§4.10), quantity, unit value, % discount and the two taxes (null: none). */
final class PurchaseLineInput
{
    #[Assert\Uuid]
    public ?string $productId = null;

    #[Assert\Uuid]
    public ?string $accountId = null;

    #[Assert\Length(max: 500)]
    public string $description = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write a quantity greater than zero, with at most four decimals.')]
    #[Assert\Regex(pattern: '/^0+(\.0+)?$/', match: false, message: 'Write a quantity greater than zero, with at most four decimals.')]
    public string $quantity = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,14}(\.\d{1,4})?$/', message: 'Write the value in pesos, with at most four decimals.')]
    public string $unitPrice = '';

    #[Assert\Regex(pattern: '/^(100(\.0{1,4})?|\d{1,2}(\.\d{1,4})?)$/', message: 'The discount is between 0 and 100, with at most four decimals.')]
    public ?string $discount = null;

    #[Assert\Uuid]
    public ?string $chargeTaxId = null;

    /** Deprecated (observations PRD §6): one retención; means withholdings: [{tax_id}]. */
    #[Assert\Uuid]
    public ?string $withholdingTaxId = null;

    /**
     * The line's retenciones: none, or one per kind (ReteFuente, ReteIVA, ReteICA). Null: the deprecated
     * withholding_tax_id decides.
     *
     * @var list<LineWithholdingInput>|null
     */
    #[Assert\Valid]
    public ?array $withholdings = null;

    /** The line as the resolver reads it (a factura de compra's or an orden de compra's). */
    public function toContents(): PurchaseLineContents
    {
        return new PurchaseLineContents(
            self::uuid($this->productId),
            self::uuid($this->accountId),
            $this->description,
            $this->quantity,
            $this->unitPrice,
            $this->discount ?? '0',
            self::uuid($this->chargeTaxId),
            self::uuid($this->withholdingTaxId),
            null === $this->withholdings ? null : array_map(static fn (LineWithholdingInput $w) => Uuid::fromString((string) $w->taxId), $this->withholdings),
        );
    }

    private static function uuid(?string $id): ?Uuid
    {
        return null === $id || '' === $id ? null : Uuid::fromString($id);
    }
}
