<?php

namespace App\Catalog\Application\Port;

use Symfony\Component\Uid\Uuid;

/** Whether documents refer to a product: the line tables of Sales and Purchasing, which Catalog never names. */
interface ProductUsage
{
    public function isUsed(Uuid $companyId, Uuid $productId): bool;
}
