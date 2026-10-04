<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Command\CommandHandler;

final class ChangeProductStatusHandler implements CommandHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(ChangeProductStatus $command): void
    {
        $product = $this->products->get($command->companyId, $command->productId);
        $command->active ? $product->reactivate() : $product->deactivate();
    }
}
