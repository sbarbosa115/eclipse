<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Query\CatalogSettings;
use App\Ledger\Application\Tax\CreateTax;
use App\Ledger\Application\Tax\DeleteTax;
use App\Ledger\Application\Tax\SetTaxActive;
use App\Ledger\Application\Tax\UpdateTax;
use App\Ledger\Domain\Error\TaxNotFound;
use App\Ledger\UI\Http\Input\TaxChangesInput;
use App\Ledger\UI\Http\Input\TaxInput;
use App\Ledger\UI\Http\Output\TaxSettingOutput;
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
 * Editing the company's taxes (§4.4): the owner and the accountant create, edit, deactivate and delete (a tax a document
 * used is only deactivated). Reading them is CatalogController's: every role.
 */
#[Route('/api/v1/taxes')]
final class TaxController extends AbstractController
{
    use EditsCatalogs;

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly CatalogSettings $settings,
    ) {
    }

    /**
     * Adds a tax. Its class and kind (IVA, impoconsumo, retefuente, reteiva, reteica) are fixed from here on.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(TaxSettingOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $input = $this->inputs->map($this->inputs->json($request), TaxInput::class);
        $id = $this->commands->dispatch(new CreateTax($user->companyId(), $user->userId(), $input->name, $input->taxClass, $input->kind, $input->calculation, $input->rate, $input->salesAccountId, $input->purchaseAccountId, $input->validFrom, $input->validTo));
        \assert($id instanceof Uuid);

        return $this->json(TaxSettingOutput::of($this->settings->tax($user->companyId(), $id)), 201);
    }

    /**
     * Changes a tax's name, calculation, rate, accounts and validity dates. "Ninguno" cannot be changed.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(TaxSettingOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $taxId = self::id($id);
        $input = $this->inputs->map($this->inputs->json($request), TaxChangesInput::class);
        $this->commands->dispatch(new UpdateTax($user->companyId(), $user->userId(), $taxId, $input->name, $input->calculation, $input->rate, $input->salesAccountId, $input->purchaseAccountId, $input->validFrom, $input->validTo));

        return $this->json(TaxSettingOutput::of($this->settings->tax($user->companyId(), $taxId)));
    }

    /**
     * Takes the tax out of the pickers; documents that used it keep it.
     */
    #[Route('/{id}/deactivate', methods: ['POST'])]
    #[ApiResponse(TaxSettingOutput::class)]
    public function deactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, false, $user);
    }

    #[Route('/{id}/activate', methods: ['POST'])]
    #[ApiResponse(TaxSettingOutput::class)]
    public function activate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, true, $user);
    }

    /**
     * Deletes a tax nothing uses: 204. A tax a document, a product or the company uses answers 409 `tax_in_use`.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->requireCatalogEditor($user);
        $this->commands->dispatch(new DeleteTax($user->companyId(), $user->userId(), self::id($id)));

        return new Response(null, 204);
    }

    private function setActive(string $id, bool $active, SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $taxId = self::id($id);
        $this->commands->dispatch(new SetTaxActive($user->companyId(), $user->userId(), $taxId, $active));

        return $this->json(TaxSettingOutput::of($this->settings->tax($user->companyId(), $taxId)));
    }

    /** An id that is not a UUID is as unknown as one nobody owns. */
    private static function id(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new TaxNotFound();
    }
}
