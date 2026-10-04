<?php

namespace App\Catalog\UI\Http\Controller;

use App\Catalog\Application\Command\CreateCategory;
use App\Catalog\Application\Command\RenameCategory;
use App\Catalog\Application\Query\CategoryCatalog;
use App\Catalog\UI\Http\CatalogAccess;
use App\Catalog\UI\Http\Input\CategoryInput;
use App\Catalog\UI\Http\Output\CategoryOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Product categories: a flat list (§9 Q20) the owner and billing users create and rename; every role reads it.
 */
#[Route('/api/v1/product-categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly CategoryCatalog $categories,
        private readonly CatalogAccess $access,
    ) {
    }

    /**
     * Every category of the company, by name, with how many products are in it.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(CategoryOutput::class, key: 'items', list: true)]
    public function list(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(CategoryOutput::of(...), $this->categories->list($user->companyId()))]);
    }

    /**
     * {name} → 201. 422 on `name` when the company has a category with it.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(CategoryOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $in = $this->inputs->map($this->inputs->json($request), CategoryInput::class);
        $id = $this->commands->dispatch(new CreateCategory($user->companyId(), $in->name));
        \assert($id instanceof Uuid);

        return $this->json(CategoryOutput::of($this->categories->get($user->companyId(), $id)), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(CategoryOutput::class)]
    public function rename(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $categoryId = $this->access->categoryId($id);
        $in = $this->inputs->map($this->inputs->json($request), CategoryInput::class);
        $this->commands->dispatch(new RenameCategory($user->companyId(), $categoryId, $in->name));

        return $this->json(CategoryOutput::of($this->categories->get($user->companyId(), $categoryId)));
    }
}
