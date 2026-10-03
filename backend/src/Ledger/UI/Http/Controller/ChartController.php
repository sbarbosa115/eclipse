<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Command\AddAccount;
use App\Ledger\Application\Command\UpdateAccount;
use App\Ledger\Application\Query\ChartQueries;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\UI\Http\Input\AddAccountInput;
use App\Ledger\UI\Http\Input\UpdateAccountInput;
use App\Ledger\UI\Http\Output\AccountOutput;
use App\Ledger\UI\Http\Security\BooksAccess;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiException;
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
 * §4.13 Plan de cuentas: every role browses it; the owner and the accountant add sub-accounts and auxiliares, rename
 * the company's own accounts and (de)activate them.
 */
#[Route('/api/v1/accounts')]
final class ChartController extends AbstractController
{
    public function __construct(
        private readonly ChartQueries $chart,
        private readonly LedgerCatalog $catalog,
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
    ) {
    }

    /**
     * The chart in code order: ?q= (digits: a code prefix; words: part of the name), ?class=1…9, ?page, ?per_page ≤ 100
     * (default 50).
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(AccountOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $class = $request->query->getString('class');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 50)));
        $result = $this->chart->page($user->companyId(), $request->query->getString('q'), 1 === preg_match('/^[1-9]$/', $class) ? $class : null, $page, $perPage);

        return $this->json(['items' => array_map(AccountOutput::of(...), $result->items), 'total' => $result->total, 'page' => $result->page, 'per_page' => $result->perPage]);
    }

    /**
     * Adds a sub-account or auxiliar: {parent_code, code (the parent's plus two digits), name, usable_on_purchases?}.
     * 409 account_code_taken; 422 account_code_invalid, parent_account_not_found; 403 for the billing user.
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(AccountOutput::class, status: 201)]
    public function add(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $input = $this->inputs->map($this->inputs->json($request), AddAccountInput::class);
        /** @var Uuid $id */
        $id = $this->commands->dispatch(new AddAccount($user->companyId(), $user->userId(), $input->parentCode, $input->code, $input->name, $input->usableOnPurchases));

        return $this->json(AccountOutput::of($this->catalog->account($user->companyId(), $id)), 201);
    }

    /**
     * {name, active, usable_on_purchases}. A PUC account keeps its name (409 account_standard); an account a posting
     * rule uses stays active (409 account_in_posting_rule); another company's id is 404.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(AccountOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $accountId = Uuid::isValid($id) ? Uuid::fromString($id) : throw ApiException::notFound();
        $input = $this->inputs->map($this->inputs->json($request), UpdateAccountInput::class);
        $this->commands->dispatch(new UpdateAccount($user->companyId(), $user->userId(), $accountId, $input->name, $input->active, $input->usableOnPurchases));

        return $this->json(AccountOutput::of($this->catalog->account($user->companyId(), $accountId)));
    }
}
