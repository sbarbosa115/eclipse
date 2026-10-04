<?php

namespace App\Catalog\Domain\Model;

/**
 * Kept from stage 1 so stage 3 can give stock to products without a migration (§4.3). A Producto bought posts to
 * compra de mercancías (6205) under the periodic system.
 */
enum ProductType: string
{
    case Product = 'producto';
    case Service = 'servicio';
}
