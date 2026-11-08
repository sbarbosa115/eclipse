<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Exogena\ExogenaConcepts;
use App\Ledger\Application\Exogena\ExogenaConceptView;
use App\Ledger\Application\Exogena\ReplaceExogenaConcepts;
use App\Ledger\UI\Http\Input\ExogenaConceptInput;
use App\Ledger\UI\Http\Input\ExogenaConceptsInput;
use App\Ledger\UI\Http\Output\ExogenaConceptOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The exógena concept table (observations PRD §4.7 d): account prefix → DIAN concept per format (1001 pagos, 1007
 * ingresos; the longest prefix of an account wins), seeded with defaults for every company. Reading is VIEW_BOOKS,
 * replacing it MANAGE_BOOKS, audited (`exogena_concepts.changed`).
 */
#[Route('/api/v1/ledger/exogena-concepts')]
final class ExogenaConceptController extends AbstractController
{
    public function __construct(
        private readonly ExogenaConcepts $concepts,
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
    ) {
    }

    /** The company's table, by format then prefix. */
    #[Route('', methods: ['GET'])]
    #[IsGranted(Permission::VIEW_BOOKS)]
    #[ApiResponse(ExogenaConceptOutput::class, key: 'items', list: true)]
    public function list(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->items($user);
    }

    /**
     * {items: [{format, account_prefix, concept_code}]} replaces the whole table (at most 500 rows; an empty list is
     * allowed). 422 by field (`items[N].format`, `.account_prefix`, `.concept_code`), and on a prefix twice in one format.
     */
    #[Route('', methods: ['PUT'])]
    #[IsGranted(Permission::MANAGE_BOOKS)]
    #[ApiResponse(ExogenaConceptOutput::class, key: 'items', list: true)]
    public function replace(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $input = $this->inputs->map($this->inputs->json($request), ExogenaConceptsInput::class);
        $rows = array_map(static fn (ExogenaConceptInput $row) => new ExogenaConceptView($row->format, $row->accountPrefix, $row->conceptCode), array_values($input->items));
        $this->commands->dispatch(new ReplaceExogenaConcepts($user->companyId(), $user->userId(), $rows));

        return $this->items($user);
    }

    private function items(SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(
            static fn (ExogenaConceptView $row) => new ExogenaConceptOutput($row->format, $row->accountPrefix, $row->conceptCode),
            $this->concepts->table($user->companyId()),
        )]);
    }
}
