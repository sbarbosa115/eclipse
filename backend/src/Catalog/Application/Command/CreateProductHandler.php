<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\ProductReferences;
use App\Catalog\Domain\Error\DuplicateCode;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductType;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money\UnitPrice;
use Symfony\Component\Uid\Uuid;

final class CreateProductHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductReferences $references,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Uuid the new product's id
     *
     * @throws DuplicateCode
     */
    public function __invoke(CreateProduct $command): Uuid
    {
        $code = trim($command->code);
        if ($this->products->codeTaken($command->companyId, $code)) {
            throw new DuplicateCode();
        }
        $taxes = $this->references->check($command->companyId, $command->categoryId, $command->chargeTaxId, $command->withholdingTaxId, $command->revenueAccountId, $command->expenseAccountId, $command->salePrice, $command->priceIncludesTax, true);

        $type = ProductType::from($command->type);
        $product = new Product(
            $command->companyId,
            $type,
            $code,
            trim($command->name),
            ProductReferences::unitCode($command->unitCode, $type),
            UnitPrice::of($command->salePrice),
            $command->priceIncludesTax,
            $taxes->chargeTaxId,
            $taxes->withholdingTaxId,
            $command->revenueAccountId,
            $command->expenseAccountId,
            $this->clock->now(),
        );
        $product->describe(self::text($command->description), $command->categoryId);
        $this->products->add($product);

        return $product->id();
    }

    public static function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
