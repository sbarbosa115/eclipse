<?php

namespace App\Purchasing\UI\Http\Controller;

use App\Purchasing\Application\Command\ConvertPurchaseOrder;
use App\Purchasing\Application\Command\CreateDraftPurchaseOrder;
use App\Purchasing\Application\Command\DeleteDraftPurchaseOrder;
use App\Purchasing\Application\Command\DuplicatePurchaseOrder;
use App\Purchasing\Application\Command\EmitPurchaseOrder;
use App\Purchasing\Application\Command\SendPurchaseOrder;
use App\Purchasing\Application\Command\UpdateDraftPurchaseOrder;
use App\Purchasing\Application\Command\VoidPurchaseOrder;
use App\Purchasing\Application\Document\PurchaseOrderPdf;
use App\Purchasing\Application\Query\PurchaseOrderFilter;
use App\Purchasing\Application\Query\PurchaseOrderQueries;
use App\Purchasing\Domain\Error\PurchaseOrderNotFound;
use App\Purchasing\UI\Http\Input\PurchaseOrderInput;
use App\Purchasing\UI\Http\Input\VoidPurchaseOrderInput;
use App\Purchasing\UI\Http\Output\PurchaseOrderOutput;
use App\Purchasing\UI\Http\Output\PurchaseOrderSummaryOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\EmailQuota;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Órdenes de compra (observations PRD §4.8): drafts, emission (series OC, no journal entry), PDF and e-mail, void,
 * duplicate, and one conversion into a draft factura de compra. Every role reads (and downloads the PDF); the owner and
 * billing users write, emit, send, void and convert (§8). An order never posts. Another company's id is a 404 everywhere.
 */
#[Route('/api/v1/purchase-orders')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class PurchaseOrderController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly PurchaseOrderQueries $orders,
        private readonly PurchaseOrderPdf $pdf,
    ) {
    }

    /**
     * Newest first: ?q= part of the number or the supplier's name (literal), ?status=draft|emitted|converted|voided,
     * ?from=, ?to= (issue date), ?tercero_id=, ?page, ?per_page ≤ 100. 400 invalid_date.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(PurchaseOrderSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $page = $this->orders->search($user->companyId(), new PurchaseOrderFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from'), 'from'),
            self::date($q->getString('to'), 'to'),
            $q->getString('tercero_id') ?: null,
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ));

        return $this->json([
            'items' => array_map(PurchaseOrderSummaryOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /** One order, whole. */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user->companyId(), $this->orderId($id));
    }

    /**
     * A draft {tercero_id, issue_date, expected_date?, notes?, lines: [{product_id | account_id, description, quantity,
     * unit_price, discount?, charge_tax_id?, withholdings?: [{tax_id}] (one per kind)}]} → 201. 422 `validation_failed`
     * by field path (`tercero_id`, `expected_date`, `lines[0].product_id`, `lines[0].withholdings`…).
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $in = $this->inputs->map($this->inputs->json($request), PurchaseOrderInput::class);
        $id = $this->commands->dispatch(new CreateDraftPurchaseOrder($user->companyId(), $user->userId(), $in->toContents()));
        \assert($id instanceof Uuid);

        return $this->present($user->companyId(), $id, 201);
    }

    /** Rewrites the draft with the same body as create. 409 document_not_draft. */
    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $orderId = $this->orderId($id);
        $in = $this->inputs->map($this->inputs->json($request), PurchaseOrderInput::class);
        $this->commands->dispatch(new UpdateDraftPurchaseOrder($user->companyId(), $orderId, $in->toContents()));

        return $this->present($user->companyId(), $orderId);
    }

    /** Deletes a draft → 204. 409 document_not_draft. */
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->commands->dispatch(new DeleteDraftPurchaseOrder($user->companyId(), $this->orderId($id)));

        return new Response(null, 204);
    }

    /** The next OC number, frozen; posts nothing. 409 document_not_draft; 422 validation_failed, supplier_inactive. */
    #[Route('/{id}/emit', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function emit(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->emitting($id, $user, false);
    }

    /** Emits and queues the PDF to the supplier's e-mail. 422 tercero_has_no_email. */
    #[Route('/{id}/emit-and-send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function emitAndSend(string $id, #[CurrentUser] SignedInUser $user, EmailQuota $quota): JsonResponse
    {
        $quota->spendOnDocument($user);

        return $this->emitting($id, $user, true);
    }

    /** Queues the PDF to the supplier → 202. 409 purchase_order_not_open; 422 tercero_has_no_email. */
    #[Route('/{id}/send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    public function send(string $id, #[CurrentUser] SignedInUser $user, EmailQuota $quota): Response
    {
        $quota->spendOnDocument($user);
        $this->commands->dispatch(new SendPurchaseOrder($user->companyId(), $this->orderId($id)));

        return new Response(null, 202);
    }

    /** {reason} → voided, number kept, nothing to reverse. 409 purchase_order_not_open. */
    #[Route('/{id}/void', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $orderId = $this->orderId($id);
        $in = $this->inputs->map($this->inputs->json($request), VoidPurchaseOrderInput::class);
        $this->commands->dispatch(new VoidPurchaseOrder($user->companyId(), $orderId, $user->userId(), $in->reason));

        return $this->present($user->companyId(), $orderId);
    }

    /** A new draft dated today with the same supplier and lines → 201. */
    #[Route('/{id}/duplicate', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class, status: 201)]
    public function duplicate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $copy = $this->commands->dispatch(new DuplicatePurchaseOrder($user->companyId(), $this->orderId($id), $user->userId()));
        \assert($copy instanceof Uuid);

        return $this->present($user->companyId(), $copy, 201);
    }

    /**
     * Once, from emitted: a draft purchase invoice with the same supplier and lines, dated today, no supplier number
     * and no formas de pago → the order, now converted, with converted_invoice_id (the invoice's purchase_order_id is
     * this order). 409 purchase_order_already_converted, purchase_order_not_open; 422 validation_failed (the invoice's
     * own violations, by field path): nothing is converted then.
     */
    #[Route('/{id}/convert', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(PurchaseOrderOutput::class)]
    public function convert(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $orderId = $this->orderId($id);
        $this->commands->dispatch(new ConvertPurchaseOrder($user->companyId(), $orderId, $user->userId()));

        return $this->present($user->companyId(), $orderId);
    }

    /** The order's PDF (ANULADA when voided, BORRADOR on a draft). */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $document = $this->pdf->render($user->companyId(), $this->orderId($id));

        return new Response($document->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $document->fileName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function emitting(string $id, SignedInUser $user, bool $send): JsonResponse
    {
        $orderId = $this->orderId($id);
        $this->commands->dispatch(new EmitPurchaseOrder($user->companyId(), $orderId, $user->userId(), $send));

        return $this->present($user->companyId(), $orderId);
    }

    private function orderId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new PurchaseOrderNotFound();
    }

    private function present(Uuid $companyId, Uuid $id, int $status = 200): JsonResponse
    {
        return $this->json(PurchaseOrderOutput::of($this->orders->get($companyId, $id)), $status);
    }

    private static function date(string $value, string $field): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw ApiException::badRequest('invalid_date', \sprintf('"%s" is a date as YYYY-MM-DD.', $field));
        }

        return $date;
    }
}
