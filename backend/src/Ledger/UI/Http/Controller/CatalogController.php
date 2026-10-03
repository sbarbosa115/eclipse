<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\UI\Http\Output\AccountOutput;
use App\Ledger\UI\Http\Output\PaymentMethodOutput;
use App\Ledger\UI\Http\Output\TaxOutput;
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
     * The company's taxes. ?class=charge|withholding; ?all=1 includes the inactive ones.
     */
    #[Route('/taxes', methods: ['GET'])]
    #[ApiResponse(TaxOutput::class, key: 'items', list: true)]
    public function taxes(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $class = $request->query->getString('class');
        $views = $this->catalog->taxes($user->companyId(), \in_array($class, ['charge', 'withholding'], true) ? $class : null, !$request->query->getBoolean('all'));

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
     * Postable accounts for a picker: ?q= matches a code prefix or part of the name; ?purchases=1 only those usable on
     * purchase lines. At most 20.
     */
    #[Route('/accounts/search', methods: ['GET'])]
    #[ApiResponse(AccountOutput::class, key: 'items', list: true)]
    public function searchAccounts(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $views = $this->catalog->searchAccounts($user->companyId(), $request->query->getString('q'), $request->query->getBoolean('purchases'));

        return $this->json(['items' => array_map(AccountOutput::of(...), $views)]);
    }
}
