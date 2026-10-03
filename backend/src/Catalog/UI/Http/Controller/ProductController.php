<?php

namespace App\Catalog\UI\Http\Controller;

use App\Catalog\UI\Http\Output\ProductOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The contract the document forms build against (item 0). The "catalog" item implements these and adds the rest of
 * the CRUD (create, update, deactivate, categories).
 */
#[Route('/api/v1/products')]
final class ProductController extends AbstractController
{
    /**
     * Search: ?q= (part of the code or name), ?type=producto|servicio, ?active=1, ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(ProductOutput::class, page: true)]
    public function list(): JsonResponse
    {
        throw ApiException::notImplemented('catalog');
    }

    /**
     * Quick-create from a document line (§4.3): {type, code, name, sale_price, price_includes_tax, charge_tax_id?,
     * withholding_tax_id?} → 201.
     */
    #[Route('/quick', methods: ['POST'])]
    #[ApiResponse(ProductOutput::class, status: 201)]
    public function quickCreate(): JsonResponse
    {
        throw ApiException::notImplemented('catalog');
    }

    /**
     * Writes "use these taxes on this product from now on" (§4.6 line tax dialog): {charge_tax_id, withholding_tax_id}.
     */
    #[Route('/{id}/taxes', methods: ['PUT'])]
    #[ApiResponse(ProductOutput::class)]
    public function setTaxes(string $id): JsonResponse
    {
        throw ApiException::notImplemented('catalog');
    }
}
