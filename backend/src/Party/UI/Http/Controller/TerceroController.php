<?php

namespace App\Party\UI\Http\Controller;

use App\Party\UI\Http\Output\ContactOutput;
use App\Party\UI\Http\Output\TerceroSummaryOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The contract the document forms build against (item 0). The "terceros" item implements these and adds the rest
 * of the CRUD (the full TerceroOutput, create, update, deactivate, export, erase).
 */
#[Route('/api/v1/terceros')]
final class TerceroController extends AbstractController
{
    /**
     * Search: ?q= (part of the name or identification, at least 3 characters on documents), ?role=cliente|proveedor|
     * empleado|otro, ?active=1, ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(TerceroSummaryOutput::class, page: true)]
    public function list(): JsonResponse
    {
        throw ApiException::notImplemented('terceros');
    }

    /**
     * Quick-create from inside a document (§4.2): {person_type, identification_type, identification_number,
     * check_digit?, first_names?, last_names?, business_name?, email?, roles: [cliente|proveedor|…]} → 201.
     */
    #[Route('/quick', methods: ['POST'])]
    #[ApiResponse(TerceroSummaryOutput::class, status: 201)]
    public function quickCreate(): JsonResponse
    {
        throw ApiException::notImplemented('terceros');
    }

    /**
     * A tercero's contacts, for the document header's Contacto.
     */
    #[Route('/{id}/contacts', methods: ['GET'])]
    #[ApiResponse(ContactOutput::class, key: 'items', list: true)]
    public function contacts(string $id): JsonResponse
    {
        throw ApiException::notImplemented('terceros');
    }
}
