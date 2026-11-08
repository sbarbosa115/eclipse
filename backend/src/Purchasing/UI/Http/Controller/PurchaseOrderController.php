<?php

namespace App\Purchasing\UI\Http\Controller;

use App\Purchasing\UI\Http\Output\PurchaseOrderOutput;
use App\Purchasing\UI\Http\Output\PurchaseOrderSummaryOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Órdenes de compra (observations PRD §4.8): drafts, emission (series OC, no journal entry), PDF and e-mail, void,
 * duplicate, and one conversion into a draft factura de compra. Contract only (item 0): the "purchase-orders" item
 * builds every route. Inputs: PurchaseOrderInput (create, update), VoidPurchaseOrderInput (void).
 */
#[Route('/api/v1/purchase-orders')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class PurchaseOrderController extends AbstractController
{
    private const ITEM = 'purchase-orders';

    /**
     * Newest first: ?q= part of the number or the supplier's name (literal), ?status=draft|emitted|converted|voided,
     * ?from=, ?to= (issue date), ?tercero_id=, ?page, ?per_page ≤ 100. 400 invalid_date.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(PurchaseOrderSummaryOutput::class, page: true)]
    public function list(): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function show(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** A draft {tercero_id, issue_date, expected_date?, notes?, lines: [...as a purchase invoice's]} → 201. */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class, status: 201)]
    public function create(): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** Rewrites the draft. 409 document_not_draft. */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function update(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** Deletes a draft → 204. 409 document_not_draft. */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** The next OC number, frozen; posts nothing. 409 document_not_draft; 422 validation_failed, supplier_inactive. */
    #[Route('/{id}/emit', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function emit(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** Emits and queues the PDF to the supplier's e-mail. 422 tercero_has_no_email. */
    #[Route('/{id}/emit-and-send', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function emitAndSend(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** Queues the PDF to the supplier → 202. 409 purchase_order_not_open; 422 tercero_has_no_email. */
    #[Route('/{id}/send', methods: ['POST'])]
    public function send(string $id): Response
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** {reason} → voided, number kept, nothing to reverse. 409 purchase_order_not_open. */
    #[Route('/{id}/void', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function void(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** A new draft dated today with the same supplier and lines → 201. */
    #[Route('/{id}/duplicate', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class, status: 201)]
    public function duplicate(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /**
     * Once, from emitted: a draft purchase invoice with the same supplier and lines, dated today, no supplier number
     * and no formas de pago → the order, now converted, with converted_invoice_id. 409 purchase_order_already_converted,
     * purchase_order_not_open; 422 validation_failed (the invoice's own violations).
     */
    #[Route('/{id}/convert', methods: ['POST'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function convert(string $id): JsonResponse
    {
        throw ApiException::notImplemented(self::ITEM);
    }

    /** The order's PDF (ANULADA when voided, BORRADOR on a draft). */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id): Response
    {
        throw ApiException::notImplemented(self::ITEM);
    }
}
