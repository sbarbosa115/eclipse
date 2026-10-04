<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A document line uses the product: it can be deactivated, never deleted (§4.3). */
final class ProductInUse extends Conflict
{
    public function __construct()
    {
        parent::__construct('product_in_use', 'A document uses this product: deactivate it instead of deleting it.');
    }
}
