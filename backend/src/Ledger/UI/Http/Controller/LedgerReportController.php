<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Query\JournalFilter;
use App\Ledger\Application\Query\JournalQueries;
use App\Ledger\Application\Report\LedgerReports;
use App\Ledger\UI\Http\Output\BalanceSheetOutput;
use App\Ledger\UI\Http\Output\IncomeStatementOutput;
use App\Ledger\UI\Http\Output\JournalEntryOutput;
use App\Ledger\UI\Http\Output\TrialBalanceOutput;
use App\Ledger\UI\Http\Security\BooksAccess;
use App\Shared\Domain\Clock;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * §4.13 The books, for the owner and the accountant (403 for the billing user): libro diario, balance de prueba, and
 * the estado de resultados and balance general derived from it (§9 Q25). Dates are YYYY-MM-DD (400 invalid_date);
 * without them, the year so far. CSV and PDF exports are the "reports" item's.
 */
#[Route('/api/v1/ledger')]
final class LedgerReportController extends AbstractController
{
    public function __construct(
        private readonly JournalQueries $journal,
        private readonly LedgerReports $reports,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Entries by date and number, each with all its lines: ?from, ?to, ?account (a code: that account and its
     * children), ?tercero_id, ?page, ?per_page ≤ 100 (default 25).
     */
    #[Route('/journal', methods: ['GET'])]
    #[ApiResponse(JournalEntryOutput::class, page: true)]
    public function journal(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $account = $request->query->getString('account');
        $tercero = $request->query->getString('tercero_id');
        $filter = new JournalFilter(
            $this->date($request, 'from'),
            $this->date($request, 'to'),
            1 === preg_match('/^\d{1,16}$/', $account) ? $account : null,
            Uuid::isValid($tercero) ? Uuid::fromString($tercero) : null,
        );
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 25)));
        $result = $this->journal->page($user->companyId(), $filter, $page, $perPage);

        return $this->json(['items' => array_map(JournalEntryOutput::of(...), $result->items), 'total' => $result->total, 'page' => $result->page, 'per_page' => $result->perPage]);
    }

    /**
     * Per account and its parents: saldo anterior, débitos, créditos and nuevo saldo for ?from–?to.
     */
    #[Route('/trial-balance', methods: ['GET'])]
    #[ApiResponse(TrialBalanceOutput::class)]
    public function trialBalance(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        [$from, $to] = $this->period($request);

        return $this->json(TrialBalanceOutput::of($this->reports->trialBalance($user->companyId(), $from, $to)));
    }

    /**
     * Ingresos, costos and gastos of ?from–?to, and the period's result.
     */
    #[Route('/income-statement', methods: ['GET'])]
    #[ApiResponse(IncomeStatementOutput::class)]
    public function incomeStatement(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        [$from, $to] = $this->period($request);

        return $this->json(IncomeStatementOutput::of($this->reports->incomeStatement($user->companyId(), $from, $to)));
    }

    /**
     * Activo, pasivo and patrimonio at ?date (default today).
     */
    #[Route('/balance-sheet', methods: ['GET'])]
    #[ApiResponse(BalanceSheetOutput::class)]
    public function balanceSheet(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        BooksAccess::assertBookkeeper($user);
        $date = $this->date($request, 'date') ?? $this->clock->now()->setTime(0, 0);

        return $this->json(BalanceSheetOutput::of($this->reports->balanceSheet($user->companyId(), $date)));
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    private function period(Request $request): array
    {
        $today = $this->clock->now()->setTime(0, 0);

        return [
            $this->date($request, 'from') ?? $today->setDate((int) $today->format('Y'), 1, 1),
            $this->date($request, 'to') ?? $today,
        ];
    }

    private function date(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = $request->query->getString($name);
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw ApiException::badRequest('invalid_date', \sprintf('"%s" must be a date as YYYY-MM-DD.', $name));
        }

        return $date;
    }
}
