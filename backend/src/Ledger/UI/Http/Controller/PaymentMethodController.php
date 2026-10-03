<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\PaymentMethod\CreatePaymentMethod;
use App\Ledger\Application\PaymentMethod\DeletePaymentMethod;
use App\Ledger\Application\PaymentMethod\SetPaymentMethodActive;
use App\Ledger\Application\PaymentMethod\UpdatePaymentMethod;
use App\Ledger\Application\Query\CatalogSettings;
use App\Ledger\Domain\Error\PaymentMethodNotFound;
use App\Ledger\UI\Http\Input\PaymentMethodChangesInput;
use App\Ledger\UI\Http\Input\PaymentMethodInput;
use App\Ledger\UI\Http\Output\PaymentMethodSettingOutput;
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
 * Editing the company's payment methods (§4.5): the owner and the accountant create, edit, deactivate and delete (a
 * method a document used is only deactivated). Reading them is CatalogController's: every role.
 */
#[Route('/api/v1/payment-methods')]
final class PaymentMethodController extends AbstractController
{
    use EditsCatalogs;

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly CatalogSettings $settings,
    ) {
    }

    /**
     * Adds a method: contado ("cash") needs a postable account, crédito ("credit") has none.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(PaymentMethodSettingOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $input = $this->inputs->map($this->inputs->json($request), PaymentMethodInput::class);
        $id = $this->commands->dispatch(new CreatePaymentMethod($user->companyId(), $user->userId(), $input->name, $input->kind, $input->accountId));
        \assert($id instanceof Uuid);

        return $this->json(PaymentMethodSettingOutput::of($this->settings->paymentMethod($user->companyId(), $id)), 201);
    }

    /**
     * Renames a method and moves its account. Its kind never changes.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(PaymentMethodSettingOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $methodId = self::id($id);
        $input = $this->inputs->map($this->inputs->json($request), PaymentMethodChangesInput::class);
        $this->commands->dispatch(new UpdatePaymentMethod($user->companyId(), $user->userId(), $methodId, $input->name, $input->accountId));

        return $this->json(PaymentMethodSettingOutput::of($this->settings->paymentMethod($user->companyId(), $methodId)));
    }

    /**
     * Takes the method out of the pickers; documents that used it keep it.
     */
    #[Route('/{id}/deactivate', methods: ['POST'])]
    #[ApiResponse(PaymentMethodSettingOutput::class)]
    public function deactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, false, $user);
    }

    #[Route('/{id}/activate', methods: ['POST'])]
    #[ApiResponse(PaymentMethodSettingOutput::class)]
    public function activate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->setActive($id, true, $user);
    }

    /**
     * Deletes a method nothing uses: 204. One a document used answers 409 `payment_method_in_use`.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->requireCatalogEditor($user);
        $this->commands->dispatch(new DeletePaymentMethod($user->companyId(), $user->userId(), self::id($id)));

        return new Response(null, 204);
    }

    private function setActive(string $id, bool $active, SignedInUser $user): JsonResponse
    {
        $this->requireCatalogEditor($user);
        $methodId = self::id($id);
        $this->commands->dispatch(new SetPaymentMethodActive($user->companyId(), $user->userId(), $methodId, $active));

        return $this->json(PaymentMethodSettingOutput::of($this->settings->paymentMethod($user->companyId(), $methodId)));
    }

    /** An id that is not a UUID is as unknown as one nobody owns. */
    private static function id(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new PaymentMethodNotFound();
    }
}
