<?php

namespace App\Purchasing\Application\Posting;

use Symfony\Component\Uid\Uuid;

/** What posting a purchase line by product needs of the product: is it goods (producto) and its own expense account. */
final readonly class ProductAccounting
{
    public function __construct(
        public bool $isGoods,
        public ?Uuid $expenseAccountId,
    ) {
    }
}
