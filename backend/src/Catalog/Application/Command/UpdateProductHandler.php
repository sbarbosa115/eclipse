<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\ProductReferences;
use App\Catalog\Domain\Error\DuplicateCode;
use App\Catalog\Domain\Model\ProductType;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Money\UnitPrice;

final class UpdateProductHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductReferences $references,
    ) {
    }

    /**
     * @throws \App\Catalog\Domain\Error\ProductNotFound
     * @throws DuplicateCode
     */
    public function __invoke(UpdateProduct $command): void
    {
        $product = $this->products->get($command->companyId, $command->productId);
        $code = trim($command->code);
        if ($this->products->codeTaken($command->companyId, $code, $product->id())) {
            throw new DuplicateCode();
        }
        $this->references->check($command->companyId, $command->categoryId, $command->chargeTaxId, $command->withholdingTaxId, $command->revenueAccountId, $command->expenseAccountId, $command->salePrice, $command->priceIncludesTax, false);

        $type = ProductType::from($command->type);
        $product->revise(
            $type,
            $code,
            trim($command->name),
            CreateProductHandler::text($command->description),
            $command->categoryId,
            ProductReferences::unitCode($command->unitCode, $type),
            UnitPrice::of($command->salePrice),
            $command->priceIncludesTax,
            $command->chargeTaxId,
            $command->withholdingTaxId,
            $command->revenueAccountId,
            $command->expenseAccountId,
        );
    }
}
