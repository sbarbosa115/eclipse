<?php

namespace App\Sales\UI\Http\Controller;

use App\Catalog\Application\Query\ProductCatalog;
use App\Sales\Application\Command\CreateDraftSalesInvoice;
use App\Sales\Application\Command\DuplicateSalesInvoice;
use App\Sales\Application\Command\EmitSalesInvoice;
use App\Sales\Application\Command\SalesInvoiceData;
use App\Sales\Application\Command\SalesInvoiceLineData;
use App\Sales\Application\Command\SalesInvoicePaymentData;
use App\Sales\Application\Command\SendSalesInvoice;
use App\Sales\Application\Command\UpdateDraftSalesInvoice;
use App\Sales\Application\Command\VoidSalesInvoice;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Application\Query\SalesInvoiceFilter;
use App\Sales\Application\Query\SalesInvoiceQueries;
use App\Sales\UI\Http\Input\SalesInvoiceInput;
use App\Sales\UI\Http\Input\SalesInvoiceLineInput;
use App\Sales\UI\Http\Input\SalesInvoicePaymentInput;
use App\Sales\UI\Http\Input\VoidInput;
use App\Sales\UI\Http\Output\SalesInvoiceOutput;
use App\Sales\UI\Http\Output\SalesInvoiceSummaryOutput;
use App\Sales\UI\Http\SalesInvoiceAccess;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Facturas de venta (§4.6, §4.8, §4.12, §4.15). Every role reads (and downloads the PDF); the owner and billing users
 * write, emit, send and void (§8: the accountant cannot emit commercial documents).
 */
#[Route('/api/v1/sales-invoices')]
final class SalesInvoiceController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly SalesInvoiceQueries $invoices,
        private readonly ProductCatalog $products,
        private readonly SalesInvoicePdf $pdf,
        private readonly SalesInvoiceAccess $access,
    ) {
    }

    /**
     * The list, newest first: ?q= (part of the number or the client's name, matched literally), ?status=draft|emitted|
     * partially_paid|paid|voided, ?from=, ?to= (fecha de elaboración, YYYY-MM-DD, both included), ?tercero_id=, ?page,
     * ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(SalesInvoiceSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $page = $this->invoices->search($user->companyId(), new SalesInvoiceFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from'), 'from'),
            self::date($q->getString('to'), 'to'),
            $q->getString('tercero_id') ?: null,
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ));

        return $this->json([
            'items' => array_map(SalesInvoiceSummaryOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /** One invoice, whole. */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(SalesInvoiceOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user->companyId(), $this->access->invoiceId($id));
    }

    /**
     * A new draft: {tercero_id, contact_id?, seller_id?, issue_date, notes?, lines: [{product_id, description, quantity,
     * unit_price, discount, charge_tax_id, withholding_tax_id}], payments: [{payment_method_id, amount, due_date?}]}
     * → 201. The formas de pago need not add up yet. 422 `validation_failed` by field path (`lines.0.product_id`…).
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(SalesInvoiceOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $id = $this->commands->dispatch(new CreateDraftSalesInvoice($user->companyId(), $user->userId(), $this->data($request)));
        \assert($id instanceof Uuid);

        return $this->present($user->companyId(), $id, 201);
    }

    /** Rewrites a draft with the same body as create. 409 `document_not_draft` once emitted. */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(SalesInvoiceOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $this->commands->dispatch(new UpdateDraftSalesInvoice($user->companyId(), $invoiceId, $this->data($request)));

        return $this->present($user->companyId(), $invoiceId);
    }

    /**
     * Emits the draft (§4.8): the resolution's next number, a receivable per crédito line, the A.1 entry. 422
     * `validation_failed` (`lines`, `issue_date` in the future, a crédito's `payments.N.due_date`),
     * `payments_do_not_match_total`, `tercero_inactive`, `resolution_missing|inactive|exhausted`; 409 `document_not_draft`,
     * `period_locked`.
     */
    #[Route('/{id}/emit', methods: ['POST'])]
    #[ApiResponse(SalesInvoiceOutput::class)]
    public function emit(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->emitting($id, $user, false);
    }

    /**
     * "Emitir y enviar": emits, then e-mails the PDF to the client's billing address through the queue. Also 422
     * `tercero_has_no_email` (nothing is emitted then).
     */
    #[Route('/{id}/emit-and-send', methods: ['POST'])]
    #[ApiResponse(SalesInvoiceOutput::class)]
    public function emitAndSend(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->emitting($id, $user, true);
    }

    /** E-mails an emitted invoice's PDF again → 202. 409 `document_not_emitted`; 422 `tercero_has_no_email`. */
    #[Route('/{id}/send', methods: ['POST'])]
    public function send(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->access->mayWrite($user);
        $this->commands->dispatch(new SendSalesInvoice($user->companyId(), $this->access->invoiceId($id)));

        return new Response(null, 202);
    }

    /**
     * Voids an emitted invoice today (§4.12): {reason}. The reversing entry is posted, the receivables leave the
     * cartera, the number is kept. 409 `document_has_allocations`, `document_not_emitted`, `period_locked`.
     */
    #[Route('/{id}/void', methods: ['POST'])]
    #[ApiResponse(SalesInvoiceOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $in = $this->inputs->map($this->inputs->json($request), VoidInput::class);
        $this->commands->dispatch(new VoidSalesInvoice($user->companyId(), $invoiceId, $user->userId(), $in->reason));

        return $this->present($user->companyId(), $invoiceId);
    }

    /** A new draft, dated today, with the invoice's client, lines and formas de pago (§4.15) → 201. */
    #[Route('/{id}/duplicate', methods: ['POST'])]
    #[ApiResponse(SalesInvoiceOutput::class, status: 201)]
    public function duplicate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $copy = $this->commands->dispatch(new DuplicateSalesInvoice($user->companyId(), $this->access->invoiceId($id), $user->userId()));
        \assert($copy instanceof Uuid);

        return $this->present($user->companyId(), $copy, 201);
    }

    /** The PDF (§4.8), ANULADA when voided, BORRADOR on a draft. */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $document = $this->pdf->render($user->companyId(), $this->access->invoiceId($id));

        return new Response($document->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $document->fileName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function emitting(string $id, SignedInUser $user, bool $send): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $this->commands->dispatch(new EmitSalesInvoice($user->companyId(), $invoiceId, $user->userId(), $send));

        return $this->present($user->companyId(), $invoiceId);
    }

    private function present(Uuid $companyId, Uuid $id, int $status = 200): JsonResponse
    {
        $invoice = $this->invoices->get($companyId, $id);
        $labels = [];
        foreach ($invoice->lines() as $line) {
            $productId = $line->productId();
            if (null === $productId || isset($labels[$productId->toRfc4122()])) {
                continue;
            }
            try {
                $product = $this->products->get($companyId, $productId);
                $labels[$product->id] = $product->code.' · '.$product->name;
            } catch (NotFound) {
                // Deleted from the catalog: the line keeps its description.
            }
        }

        return $this->json(SalesInvoiceOutput::of($invoice, $labels, $this->invoices->receivables($companyId, $id)), $status);
    }

    private function data(Request $request): SalesInvoiceData
    {
        $in = $this->inputs->map($this->inputs->json($request), SalesInvoiceInput::class);

        return new SalesInvoiceData(
            Uuid::fromString($in->terceroId),
            SalesInvoiceAccess::optionalUuid($in->contactId),
            SalesInvoiceAccess::optionalUuid($in->sellerId),
            new \DateTimeImmutable($in->issueDate),
            $in->notes,
            array_map(static fn (SalesInvoiceLineInput $l) => new SalesInvoiceLineData(
                SalesInvoiceAccess::optionalUuid($l->productId), $l->description, $l->quantity, $l->unitPrice, $l->discount,
                SalesInvoiceAccess::optionalUuid($l->chargeTaxId), SalesInvoiceAccess::optionalUuid($l->withholdingTaxId),
            ), $in->lines),
            array_map(static fn (SalesInvoicePaymentInput $p) => new SalesInvoicePaymentData(
                Uuid::fromString($p->paymentMethodId), $p->amount, null === $p->dueDate ? null : new \DateTimeImmutable($p->dueDate),
            ), $in->payments),
        );
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
