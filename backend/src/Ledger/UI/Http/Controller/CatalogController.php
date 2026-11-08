<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\UI\Http\Output\AccountOutput;
use App\Ledger\UI\Http\Output\AccountSearchOutput;
use App\Ledger\UI\Http\Output\PaymentMethodOutput;
use App\Ledger\UI\Http\Output\TaxOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * What document forms pick from: taxes, payment methods and accounts. Every role reads them; editing them is the
 * "ledger" and "taxes-payments" items' (their own controllers).
 */
#[Route('/api/v1')]
final class CatalogController extends AbstractController
{
    public function __construct(private readonly LedgerCatalog $catalog)
    {
    }

    /**
     * The company's taxes. ?class=charge|withholding; ?all=1 includes the inactive ones; ?on=YYYY-MM-DD only those in
     * force that day (a document's date: no validity dates means always in force). 400 invalid_date for another format.
     */
    #[Route('/taxes', methods: ['GET'])]
    #[ApiResponse(TaxOutput::class, key: 'items', list: true)]
    public function taxes(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $class = $request->query->getString('class');
        $views = $this->catalog->taxes($user->companyId(), \in_array($class, ['charge', 'withholding'], true) ? $class : null, !$request->query->getBoolean('all'));
        $on = $this->day($request, 'on');
        if (null !== $on) {
            $views = array_values(array_filter($views, static fn ($tax): bool => $tax->isValidOn($on)));
        }

        return $this->json(['items' => array_map(TaxOutput::of(...), $views)]);
    }

    /**
     * The company's payment methods. ?all=1 includes the inactive ones.
     */
    #[Route('/payment-methods', methods: ['GET'])]
    #[ApiResponse(PaymentMethodOutput::class, key: 'items', list: true)]
    public function paymentMethods(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(PaymentMethodOutput::of(...), $this->catalog->paymentMethods($user->companyId(), !$request->query->getBoolean('all')))]);
    }

    /**
     * Postable accounts for a picker, in code order.
     *
     * ?q= words that must all match (a number as a code prefix, anything else as part of the name); ?purchases=1 only
     * those usable on purchase lines; ?limit= 1…200 (default 50). A subcuenta with active auxiliares is not offered.
     * `total` counts every match.
     */
    #[Route('/accounts/search', methods: ['GET'])]
    #[ApiResponse(AccountSearchOutput::class)]
    public function searchAccounts(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $limit = min(200, max(1, $request->query->getInt('limit', 50)));
        $matches = $this->catalog->searchAccounts($user->companyId(), $request->query->getString('q'), $request->query->getBoolean('purchases'), $limit);

        return $this->json(new AccountSearchOutput(array_map(AccountOutput::of(...), $matches->items), $matches->total));
    }

    private function day(Request $request, string $name): ?\DateTimeImmutable
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
