<?php

namespace App\Party\UI\Http\Controller;

use App\Ledger\Application\Query\AccountView;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Party\Application\Command\CreateTercero;
use App\Party\Application\Command\DeactivateTercero;
use App\Party\Application\Command\DeleteTercero;
use App\Party\Application\Command\EraseTercero;
use App\Party\Application\Command\ExportTercero;
use App\Party\Application\Command\ReactivateTercero;
use App\Party\Application\Command\UpdateTercero;
use App\Party\Application\Query\TerceroQueries;
use App\Party\Domain\Error\TerceroNotFound;
use App\Party\Domain\Model\Contact;
use App\Party\Domain\Model\Tercero;
use App\Party\UI\Http\Input\TerceroInput;
use App\Party\UI\Http\Input\TerceroQuickInput;
use App\Party\UI\Http\Output\ContactOutput;
use App\Party\UI\Http\Output\TerceroExportOutput;
use App\Party\UI\Http\Output\TerceroOutput;
use App\Party\UI\Http\Output\TerceroSummaryOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Terceros (§4.2). Writing them is Permission::WRITE_DOCUMENTS (§8). Another company's id is
 * a 404.
 */
#[Route('/api/v1/terceros')]
final class TerceroController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly TerceroQueries $queries,
        private readonly LedgerCatalog $ledger,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Search: ?q= (part of the name or identification, at least 3 characters on documents), ?role=cliente|proveedor|
     * empleado|otro, ?active=1 (only active) or 0 (only inactive), ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(TerceroSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $active = $request->query->has('active') && '' !== $request->query->getString('active') ? $request->query->getBoolean('active') : null;
        $page = $this->queries->search(
            $user->companyId(),
            $request->query->getString('q'),
            $request->query->getString('role') ?: null,
            $active,
            max(1, $request->query->getInt('page', 1)),
            $request->query->getInt('per_page', 25),
        );

        return $this->json(['items' => array_map(TerceroSummaryOutput::of(...), $page->items), 'total' => $page->total, 'page' => $page->page, 'per_page' => $page->perPage]);
    }

    /**
     * The full form: creates the tercero (datos básicos, facturación y envío, responsabilidades, contactos, cuentas).
     * 422 `duplicate_identification` (violation on `identification_number`) when tipo + número + sucursal exist.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(TerceroOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $input = $this->inputs->map($this->inputs->json($request), TerceroInput::class);
        $id = $this->dispatch(new CreateTercero($user->companyId(), $input->toProfile()));

        return $this->present($user, $id, 201);
    }

    /**
     * Quick-create from inside a document (§4.2): {person_type, identification_type, identification_number,
     * check_digit?, first_names?, last_names?, business_name?, email, roles: [cliente|proveedor|…]} → 201.
     */
    #[Route('/quick', methods: ['POST'])]
    #[ApiResponse(TerceroSummaryOutput::class, status: 201)]
    public function quickCreate(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $input = $this->inputs->map($this->inputs->json($request), TerceroQuickInput::class);
        $id = $this->dispatch(new CreateTercero($user->companyId(), $input->toProfile()));

        return $this->json(TerceroSummaryOutput::of($this->queries->get($user->companyId(), $id)), 201);
    }

    /**
     * A tercero's contacts, for the document header's Contacto.
     */
    #[Route('/{id}/contacts', methods: ['GET'])]
    #[ApiResponse(ContactOutput::class, key: 'items', list: true)]
    public function contacts(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $tercero = $this->queries->get($user->companyId(), $this->uuid($id));

        return $this->json(['items' => array_map(static fn (Contact $c) => new ContactOutput($c->id()->toRfc4122(), $c->name(), $c->email(), $c->phone()), $tercero->contacts())]);
    }

    /**
     * One tercero with everything on its form.
     */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(TerceroOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user, $this->uuid($id));
    }

    /**
     * Replaces the tercero's data (the whole form is sent). Contacts with an `id` are kept, the ones left out are
     * removed. 422 `duplicate_identification`; 422 `tercero_erased` once its personal data was erased.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(TerceroOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $terceroId = $this->uuid($id);
        $input = $this->inputs->map($this->inputs->json($request), TerceroInput::class);
        $this->dispatch(new UpdateTercero($user->companyId(), $terceroId, $input->toProfile()));

        return $this->present($user, $terceroId);
    }

    /**
     * Deletes a tercero no document references; 409 `tercero_in_use` otherwise (deactivate it instead).
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->requireWriter($user);
        $this->dispatch(new DeleteTercero($user->companyId(), $this->uuid($id)));

        return new Response(status: 204);
    }

    /**
     * Stops offering the tercero on new documents. Existing documents are untouched.
     */
    #[Route('/{id}/deactivate', methods: ['POST'])]
    #[ApiResponse(TerceroOutput::class)]
    public function deactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $terceroId = $this->uuid($id);
        $this->dispatch(new DeactivateTercero($user->companyId(), $terceroId));

        return $this->present($user, $terceroId);
    }

    #[Route('/{id}/reactivate', methods: ['POST'])]
    #[ApiResponse(TerceroOutput::class)]
    public function reactivate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $terceroId = $this->uuid($id);
        $this->dispatch(new ReactivateTercero($user->companyId(), $terceroId));

        return $this->present($user, $terceroId);
    }

    /**
     * Ley 1581 de 2012: the tercero's personal data as JSON (served as a download). Leaves an audit-log entry.
     */
    #[Route('/{id}/export', methods: ['GET'])]
    #[ApiResponse(TerceroExportOutput::class)]
    public function export(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        /** @var Tercero $tercero */
        $tercero = $this->dispatch(new ExportTercero($user->companyId(), $user->userId(), $this->uuid($id)));
        $now = $this->clock->now();

        $response = $this->json(new TerceroExportOutput($now->format(\DATE_ATOM), $this->output($user, $tercero)));
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="tercero-%s.json"', $tercero->id()->toRfc4122()));

        return $response;
    }

    /**
     * Ley 1581 de 2012: blanks the tercero's personal data and its contacts and deactivates it; the row stays (its
     * documents name it), with its identification and `erased_at`. Cannot be undone.
     */
    #[Route('/{id}/erase', methods: ['POST'])]
    #[ApiResponse(TerceroOutput::class)]
    public function erase(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireWriter($user);
        $terceroId = $this->uuid($id);
        $this->dispatch(new EraseTercero($user->companyId(), $user->userId(), $terceroId));

        return $this->present($user, $terceroId);
    }

    private function requireWriter(SignedInUser $user): void
    {
        if (!Permission::granted($user->role(), Permission::WRITE_DOCUMENTS)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function uuid(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new TerceroNotFound();
    }

    private function dispatch(object $command): mixed
    {
        return $this->commands->dispatch($command);
    }

    private function present(SignedInUser $user, Uuid $id, int $status = 200): JsonResponse
    {
        return $this->json($this->output($user, $this->queries->get($user->companyId(), $id)), $status);
    }

    private function output(SignedInUser $user, Tercero $t): TerceroOutput
    {
        return TerceroOutput::of($t, $this->account($user, $t->receivableAccountId()), $this->account($user, $t->payableAccountId()));
    }

    private function account(SignedInUser $user, ?Uuid $id): ?AccountView
    {
        if (null === $id) {
            return null;
        }
        try {
            return $this->ledger->account($user->companyId(), $id);
        } catch (NotFound) {
            return null;
        }
    }
}
