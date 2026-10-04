<?php

namespace App\Catalog\Application;

use App\Catalog\Domain\Model\ProductType;
use App\Catalog\Domain\Model\UnitOfMeasure;
use App\Catalog\Domain\Pricing\PriceNetOfTax;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Company\Application\Query\Companies;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Application\Query\TaxView;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxRate;
use Symfony\Component\Uid\Uuid;

/**
 * What a product points at, checked (§4.3): its category, its default taxes (active ones of the right class; the
 * company's own defaults when a new product names none) and its accounts (postable). Every wrong value is reported
 * against its own field, all at once.
 */
final class ProductReferences
{
    public function __construct(
        private readonly LedgerCatalog $ledger,
        private readonly Companies $companies,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * @param bool $fillDefaults a new product: a tax it does not name is the company's default (Configuración)
     *
     * @throws InvalidValues
     */
    public function check(
        Uuid $companyId,
        ?Uuid $categoryId,
        ?Uuid $chargeTaxId,
        ?Uuid $withholdingTaxId,
        ?Uuid $revenueAccountId,
        ?Uuid $expenseAccountId,
        string $salePrice,
        bool $priceIncludesTax,
        bool $fillDefaults,
    ): CheckedReferences {
        $violations = [];

        if (null !== $categoryId && !$this->categories->exists($companyId, $categoryId)) {
            $violations[] = ['field' => 'category_id', 'message' => 'This category does not exist.'];
        }

        if ($fillDefaults && (null === $chargeTaxId || null === $withholdingTaxId)) {
            $company = $this->companies->view($companyId);
            $chargeTaxId ??= null === $company->defaultChargeTaxId ? null : Uuid::fromString($company->defaultChargeTaxId);
            $withholdingTaxId ??= null === $company->defaultWithholdingTaxId ? null : Uuid::fromString($company->defaultWithholdingTaxId);
        }

        $charge = $this->tax($companyId, $chargeTaxId, 'charge', 'charge_tax_id', 'Choose an active charge tax (IVA, impoconsumo).', $violations);
        $this->tax($companyId, $withholdingTaxId, 'withholding', 'withholding_tax_id', 'Choose an active withholding tax (retención).', $violations);
        $this->account($companyId, $revenueAccountId, 'revenue_account_id', $violations);
        $this->account($companyId, $expenseAccountId, 'expense_account_id', $violations);

        if ([] === $violations && null !== $charge && $priceIncludesTax) {
            try {
                PriceNetOfTax::of(UnitPrice::of($salePrice), true, self::rateOf($charge));
            } catch (\InvalidArgumentException) {
                $violations[] = ['field' => 'sale_price', 'message' => 'The price is lower than the tax per unit it includes.'];
            }
        }

        if ([] !== $violations) {
            throw new InvalidValues($violations);
        }

        return new CheckedReferences($chargeTaxId, $withholdingTaxId);
    }

    /** Only the unit's code: an unknown one never reaches here (the input refuses it); a missing one is the type's. */
    public static function unitCode(?string $code, ProductType $type): string
    {
        return $code ?? UnitOfMeasure::defaultFor($type)->value;
    }

    public static function rateOf(TaxView $tax): TaxRate
    {
        return 'per_unit' === $tax->calculation ? TaxRate::perUnit($tax->rate) : TaxRate::percentage($tax->rate);
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private function tax(Uuid $companyId, ?Uuid $id, string $class, string $field, string $message, array &$violations): ?TaxView
    {
        if (null === $id) {
            return null;
        }
        try {
            $tax = $this->ledger->tax($companyId, $id);
        } catch (NotFound) {
            $violations[] = ['field' => $field, 'message' => $message];

            return null;
        }
        if (!$tax->active || $tax->taxClass !== $class) {
            $violations[] = ['field' => $field, 'message' => $message];

            return null;
        }

        return $tax;
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private function account(Uuid $companyId, ?Uuid $id, string $field, array &$violations): void
    {
        if (null === $id) {
            return;
        }
        try {
            $account = $this->ledger->account($companyId, $id);
        } catch (NotFound) {
            $violations[] = ['field' => $field, 'message' => 'Choose a postable account of the chart.'];

            return;
        }
        if (!$account->active || !$account->postable) {
            $violations[] = ['field' => $field, 'message' => 'Choose a postable account of the chart.'];
        }
    }
}
