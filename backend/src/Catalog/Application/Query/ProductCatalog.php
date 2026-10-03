<?php

namespace App\Catalog\Application\Query;

use Symfony\Component\Uid\Uuid;

interface ProductCatalog
{
    /** @throws \App\Catalog\Domain\Error\ProductNotFound */
    public function get(Uuid $companyId, Uuid $productId): ProductView;

    /**
     * The company's products and services by name, filtered and paginated.
     *
     * @param string|null $query  part of the código or the name, matched literally
     * @param string|null $type   producto or servicio
     * @param bool|null   $active true only the active ones, false only the inactive, null all
     */
    public function search(Uuid $companyId, ?string $query, ?string $type, ?bool $active, int $page, int $perPage): ProductPage;
}
