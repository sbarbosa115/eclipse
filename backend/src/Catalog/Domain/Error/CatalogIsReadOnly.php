<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** The accountant reads the catalog; the owner and billing users write it (§8). */
final class CatalogIsReadOnly extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('forbidden', 'Your role cannot change products or categories.');
    }
}
