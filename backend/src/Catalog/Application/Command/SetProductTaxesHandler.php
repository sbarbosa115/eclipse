<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\ProductReferences;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Command\CommandHandler;

final class SetProductTaxesHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductReferences $references,
    ) {
    }

    public function __invoke(SetProductTaxes $command): void
    {
        $product = $this->products->get($command->companyId, $command->productId);
        $this->references->check($command->companyId, null, $command->chargeTaxId, $command->withholdingTaxId, null, null, $product->salePrice()->toString(), $product->priceIncludesTax(), false);
        $product->useTaxes($command->chargeTaxId, $command->withholdingTaxId);
    }
}
