<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Application\Port\ProductUsage;
use App\Catalog\Domain\Error\ProductInUse;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeleteProductHandler implements CommandHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
    ) {
    }

    /**
     * @throws ProductInUse a document line uses it (§4.3): deactivate it instead
     */
    public function __invoke(DeleteProduct $command): void
    {
        $product = $this->products->get($command->companyId, $command->productId);
        if ($this->usage->isUsed($command->companyId, $product->id())) {
            throw new ProductInUse();
        }
        $this->products->remove($product);
    }
}
