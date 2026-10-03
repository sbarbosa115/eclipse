<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Command\ChangePostingRule;
use App\Ledger\Application\Command\MoveLockDate;
use App\Ledger\Application\Query\BookSettingsQueries;
use App\Ledger\Domain\Error\PostingRuleNotFound;
use App\Ledger\UI\Http\Input\ChangePostingRuleInput;
use App\Ledger\UI\Http\Input\LockDateInput;
use App\Ledger\UI\Http\Output\LockDateOutput;
use App\Ledger\UI\Http\Output\PostingRuleOutput;
use App\Ledger\UI\Http\Security\BooksAccess;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Accounting\PostingConcept;
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
 * The books' settings (§5 posting rules, §4.1 fecha de bloqueo): every role reads them; the owner and the accountant
 * change them, and each change is written to the audit log.
 */
#[Route('/api/v1')]
final class BookSettingsController extends AbstractController
{
    public function __construct(
        private readonly BookSettingsQueries $settings,
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
    ) {
    }

    /**
     * Every concept with the account it posts to, in the order of §5.
     */
    #[Route('/posting-rules', methods: ['GET'])]
    #[ApiResponse(PostingRuleOutput::class, key: 'items', list: true)]
    public function postingRules(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(PostingRuleOutput::of(...), $this->settings->postingRules($user->companyId()))]);
    }

    /**
     * Points a concept at another account: {account_id}. The account must be postable (409 account_not_postable) and
     * within the concept's part of the PUC (422 account_not_allowed_for_concept). 404 for an unknown concept or
     * another company's account.
     */
    #[Route('/posting-rules/{concept}', methods: ['PUT'])]
    #[ApiResponse(PostingRuleOutput::class)]
    public function changePostingRule(string $concept, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $known = PostingConcept::tryFrom($concept) ?? throw new PostingRuleNotFound();
        $input = $this->inputs->map($this->inputs->json($request), ChangePostingRuleInput::class);
        $this->commands->dispatch(new ChangePostingRule($user->companyId(), $user->userId(), $known, Uuid::fromString($input->accountId)));

        return $this->json(PostingRuleOutput::of($this->settings->postingRule($user->companyId(), $known->value)));
    }

    /**
     * The fecha de bloqueo contable: nothing is emitted or voided on or before it.
     */
    #[Route('/ledger/lock-date', methods: ['GET'])]
    #[ApiResponse(LockDateOutput::class)]
    public function lockDate(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(new LockDateOutput($this->settings->lockedUntil($user->companyId())));
    }

    /**
     * Moves the fecha de bloqueo: {locked_until: YYYY-MM-DD}, today at the latest (422 lock_date_in_future).
     */
    #[Route('/ledger/lock-date', methods: ['PUT'])]
    #[ApiResponse(LockDateOutput::class)]
    public function moveLockDate(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $input = $this->inputs->map($this->inputs->json($request), LockDateInput::class);
        $this->commands->dispatch(new MoveLockDate($user->companyId(), $user->userId(), new \DateTimeImmutable($input->lockedUntil)));

        return $this->json(new LockDateOutput($this->settings->lockedUntil($user->companyId())));
    }
}
