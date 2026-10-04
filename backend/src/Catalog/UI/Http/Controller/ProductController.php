<?php

namespace App\Catalog\UI\Http\Controller;

use App\Catalog\Application\Command\ChangeProductStatus;
use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Application\Command\DeleteProduct;
use App\Catalog\Application\Command\SetProductTaxes;
use App\Catalog\Application\Command\UpdateProduct;
use App\Catalog\Application\Query\ProductCatalog;
use App\Catalog\Domain\Model\UnitOfMeasure;
use App\Catalog\UI\Http\CatalogAccess;
use App\Catalog\UI\Http\Input\ProductInput;
use App\Catalog\UI\Http\Input\ProductTaxesInput;
use App\Catalog\UI\Http\Input\QuickProductInput;
use App\Catalog\UI\Http\Output\ProductOutput;
use App\Catalog\UI\Http\Output\UnitOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Products and services (§4.3). Every role reads; the owner and billing users write. The list, quick-create and
 * "use these taxes from now on" are the contract the document forms build against.
 */
#[Route('/api/v1/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly ProductCatalog $products,
        private readonly CatalogAccess $access,
    ) {
    }

    /**
     * Search: ?q= (part of the code or name), ?type=producto|servicio, ?active=1 (only active) or ?active=0 (only
     * inactive), ?page, ?per_page ≤ 100. By name.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(ProductOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $active = $q->has('active') && '' !== $q->getString('active') ? $q->getBoolean('active') : null;
        $page = $this->products->search($user->companyId(), $q->getString('q'), $q->getString('type') ?: null, $active, max(1, $q->getInt('page', 1)), $q->getInt('per_page', 20));

        return $this->json([
            'items' => array_map(ProductOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /**
     * The unidades de medida a product can use: the short DIAN list (94 unidad, KGM kilogramo, MTR metro, HUR hora,
     * ZZ servicio).
     */
    #[Route('/units', methods: ['GET'])]
    #[ApiResponse(UnitOutput::class, key: 'items', list: true)]
    public function units(): JsonResponse
    {
        return $this->json(['items' => array_map(UnitOutput::of(...), UnitOfMeasure::cases())]);
    }

    /**
     * One product or service.
     */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(ProductOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $this->access->productId($id))));
    }

    /**
     * The full form: {type, code, name, description?, category_id?, unit_code? (the type's default), sale_price,
     * price_includes_tax, charge_tax_id?, withholding_tax_id? (the company's defaults when missing), revenue_account_id?,
     * expense_account_id?} → 201. 422 `duplicate_code` is a `code` violation.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(ProductOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $in = $this->inputs->map($this->inputs->json($request), ProductInput::class);
        $id = $this->commands->dispatch(new CreateProduct(
            $user->companyId(), $in->type, $in->code, $in->name, $in->description, CatalogAccess::optionalUuid($in->categoryId), $in->unitCode, $in->salePrice, $in->priceIncludesTax,
            CatalogAccess::optionalUuid($in->chargeTaxId), CatalogAccess::optionalUuid($in->withholdingTaxId), CatalogAccess::optionalUuid($in->revenueAccountId), CatalogAccess::optionalUuid($in->expenseAccountId),
        ));
        \assert($id instanceof Uuid);

        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $id)), 201);
    }

    /**
     * Quick-create from a document line (§4.3): {type, code, name, sale_price, price_includes_tax, charge_tax_id?,
     * withholding_tax_id?} → 201. The taxes missing are the company's defaults; the unit is the type's.
     */
    #[Route('/quick', methods: ['POST'])]
    #[ApiResponse(ProductOutput::class, status: 201)]
    public function quickCreate(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $in = $this->inputs->map($this->inputs->json($request), QuickProductInput::class);
        $id = $this->commands->dispatch(new CreateProduct(
            $user->companyId(), $in->type, $in->code, $in->name, null, null, null, $in->salePrice, $in->priceIncludesTax,
            CatalogAccess::optionalUuid($in->chargeTaxId), CatalogAccess::optionalUuid($in->withholdingTaxId), null, null,
        ));
        \assert($id instanceof Uuid);

        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $id)), 201);
    }

    /**
     * Rewrites the product with the full form's fields (as in create); a missing tax or account is "none", not the
     * company's default.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(ProductOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $productId = $this->access->productId($id);
        $in = $this->inputs->map($this->inputs->json($request), ProductInput::class);
        $this->commands->dispatch(new UpdateProduct(
            $user->companyId(), $productId, $in->type, $in->code, $in->name, $in->description, CatalogAccess::optionalUuid($in->categoryId), $in->unitCode, $in->salePrice, $in->priceIncludesTax,
            CatalogAccess::optionalUuid($in->chargeTaxId), CatalogAccess::optionalUuid($in->withholdingTaxId), CatalogAccess::optionalUuid($in->revenueAccountId), CatalogAccess::optionalUuid($in->expenseAccountId),
        ));

        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $productId)));
    }

    /**
     * Writes "use these taxes on this product from now on" (§4.6 line tax dialog): {charge_tax_id, withholding_tax_id};
     * a null is "no tax".
     */
    #[Route('/{id}/taxes', methods: ['PUT'])]
    #[ApiResponse(ProductOutput::class)]
    public function setTaxes(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $productId = $this->access->productId($id);
        $in = $this->inputs->map($this->inputs->json($request), ProductTaxesInput::class);
        $this->commands->dispatch(new SetProductTaxes($user->companyId(), $productId, CatalogAccess::optionalUuid($in->chargeTaxId), CatalogAccess::optionalUuid($in->withholdingTaxId)));

        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $productId)));
    }

    /**
     * Takes the product out of the pickers of new documents; the documents that used it keep it.
     */
    #[Route('/{id}/deactivate', methods: ['POST'])]
    #[ApiResponse(ProductOutput::class)]
    public function deactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->changeStatus($id, $user, false);
    }

    #[Route('/{id}/reactivate', methods: ['POST'])]
    #[ApiResponse(ProductOutput::class)]
    public function reactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->changeStatus($id, $user, true);
    }

    /**
     * Deletes a product no document has used → 204. 409 `product_in_use` otherwise: deactivate it instead.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->access->mayWrite($user);
        $this->commands->dispatch(new DeleteProduct($user->companyId(), $this->access->productId($id)));

        return new Response(null, 204);
    }

    private function changeStatus(string $id, SignedInUser $user, bool $active): JsonResponse
    {
        $this->access->mayWrite($user);
        $productId = $this->access->productId($id);
        $this->commands->dispatch(new ChangeProductStatus($user->companyId(), $productId, $active));

        return $this->json(ProductOutput::of($this->products->get($user->companyId(), $productId)));
    }
}
