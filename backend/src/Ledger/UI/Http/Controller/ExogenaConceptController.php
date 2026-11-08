<?php

namespace App\Ledger\UI\Http\Controller;

use App\Ledger\UI\Http\Output\ExogenaConceptOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The exógena concept table (observations PRD §4.7 d): account prefix → DIAN concept per format, seeded with
 * defaults. Reading is VIEW_BOOKS, replacing it MANAGE_BOOKS (audited `exogena_concepts.changed`). Contract only:
 * the "exogena" item builds it. Input: ExogenaConceptsInput.
 */
#[Route('/api/v1/ledger/exogena-concepts')]
final class ExogenaConceptController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[IsGranted(Permission::VIEW_BOOKS)]
    #[ApiResponse(ExogenaConceptOutput::class, key: 'items', list: true)]
    public function list(): JsonResponse
    {
        throw ApiException::notImplemented('exogena');
    }

    /** {items: [{format, account_prefix, concept_code}]} replaces the table. 422 by field. */
    #[Route('', methods: ['PUT'])]
    #[IsGranted(Permission::MANAGE_BOOKS)]
    #[ApiResponse(ExogenaConceptOutput::class, key: 'items', list: true)]
    public function replace(): JsonResponse
    {
        throw ApiException::notImplemented('exogena');
    }
}
