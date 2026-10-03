<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\Application\Query\CatalogSettings;
use App\Ledger\UI\Http\Output\PaymentMethodSettingOutput;
use App\Ledger\UI\Http\Output\TaxSettingOutput;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * What the Impuestos and Formas de pago tabs of Configuración list: everything, inactive included, with the accounts'
 * names and whether a document uses each row. Every role reads.
 */
#[Route('/api/v1/settings')]
final class CatalogSettingsController extends AbstractController
{
    public function __construct(private readonly CatalogSettings $settings)
    {
    }

    /**
     * Every tax of the company, by class then name.
     */
    #[Route('/taxes', methods: ['GET'])]
    #[ApiResponse(TaxSettingOutput::class, key: 'items', list: true)]
    public function taxes(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(TaxSettingOutput::of(...), $this->settings->taxes($user->companyId()))]);
    }

    /**
     * Every payment method of the company, by name.
     */
    #[Route('/payment-methods', methods: ['GET'])]
    #[ApiResponse(PaymentMethodSettingOutput::class, key: 'items', list: true)]
    public function paymentMethods(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(PaymentMethodSettingOutput::of(...), $this->settings->paymentMethods($user->companyId()))]);
    }
}
