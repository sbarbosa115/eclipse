<?php

namespace App\Sales\UI\Http\Controller;

use App\Catalog\Application\Query\ProductCatalog;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Command\AcceptQuotation;
use App\Sales\Application\Command\ConvertQuotation;
use App\Sales\Application\Command\CreateDraftQuotation;
use App\Sales\Application\Command\DuplicateQuotation;
use App\Sales\Application\Command\EmitQuotation;
use App\Sales\Application\Command\QuotationData;
use App\Sales\Application\Command\RejectQuotation;
use App\Sales\Application\Command\SalesInvoiceLineData;
use App\Sales\Application\Command\SendQuotation;
use App\Sales\Application\Command\UpdateDraftQuotation;
use App\Sales\Application\Command\VoidQuotation;
use App\Sales\Application\Document\QuotationPdf;
use App\Sales\Application\Query\QuotationFilter;
use App\Sales\Application\Query\QuotationQueries;
use App\Sales\Application\SalesCalendar;
use App\Sales\Domain\Error\QuotationNotFound;
use App\Sales\UI\Http\Input\QuotationInput;
use App\Sales\UI\Http\Input\SalesInvoiceLineInput;
use App\Sales\UI\Http\Input\VoidInput;
use App\Sales\UI\Http\Output\QuotationOutput;
use App\Sales\UI\Http\Output\QuotationSummaryOutput;
use App\Sales\UI\Http\SalesInvoiceAccess;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
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
 * Cotizaciones (§4.6, §4.7, §4.15). Every role reads (and downloads the PDF); the owner and billing users write, emit,
 * send, decide and void (the voter, §8: the accountant cannot emit commercial documents). A cotización never posts.
 */
#[Route('/api/v1/quotations')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class QuotationController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly QuotationQueries $quotations,
        private readonly ProductCatalog $products,
        private readonly TerceroDirectory $terceros,
        private readonly QuotationPdf $pdf,
        private readonly SalesCalendar $calendar,
    ) {
    }

    /**
     * The list, newest first: ?q= (part of the number or the client's name, matched literally), ?status=draft|emitted|
     * accepted|rejected|expired|voided (an emitted quotation past its vencimiento is `expired`), ?from=, ?to= (fecha de
     * elaboración, YYYY-MM-DD, both included), ?tercero_id=, ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(QuotationSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $today = $this->calendar->today();
        $page = $this->quotations->search($user->companyId(), new QuotationFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from'), 'from'),
            self::date($q->getString('to'), 'to'),
            $q->getString('tercero_id') ?: null,
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ), $today);

        return $this->json([
            'items' => array_map(static fn ($item) => QuotationSummaryOutput::of($item, $today), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /** One quotation, whole. */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(QuotationOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user->companyId(), $this->quotationId($id));
    }

    /**
     * A new draft: {tercero_id, contact_id?, responsible_id?, issue_date, expiry_date? (default: 30 days), header?, terms?,
     * notes?, lines: [{product_id, description, quantity, unit_price, discount, charge_tax_id, withholding_tax_id}]} → 201.
     * 422 `validation_failed` by field path (`lines.0.product_id`, `expiry_date`…).
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $id = $this->commands->dispatch(new CreateDraftQuotation($user->companyId(), $user->userId(), $this->data($request)));
        \assert($id instanceof Uuid);

        return $this->present($user->companyId(), $id, 201);
    }

    /** Rewrites a draft with the same body as create. 409 `document_not_draft` once emitted. */
    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $this->commands->dispatch(new UpdateDraftQuotation($user->companyId(), $quotationId, $this->data($request)));

        return $this->present($user->companyId(), $quotationId);
    }

    /**
     * Emits the draft (§4.7): the next number of series C, frozen; no journal entry. 422 `validation_failed` (`lines`,
     * `issue_date` in the future), `tercero_inactive`; 409 `document_not_draft`.
     */
    #[Route('/{id}/emit', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function emit(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->emitting($id, $user, false);
    }

    /** "Emitir y enviar": emits, then e-mails the PDF to the client through the queue. Also 422 `tercero_has_no_email`. */
    #[Route('/{id}/emit-and-send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function emitAndSend(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->emitting($id, $user, true);
    }

    /** E-mails an emitted quotation's PDF again → 202. 409 `quotation_not_open`; 422 `tercero_has_no_email`. */
    #[Route('/{id}/send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    public function send(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->commands->dispatch(new SendQuotation($user->companyId(), $this->quotationId($id)));

        return new Response(null, 202);
    }

    /** The client accepted: emitted → accepted. 409 `quotation_not_open` (a draft, decided, voided or expired one). */
    #[Route('/{id}/accept', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function accept(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $this->commands->dispatch(new AcceptQuotation($user->companyId(), $quotationId));

        return $this->present($user->companyId(), $quotationId);
    }

    /** The client declined: emitted → rejected. 409 `quotation_not_open`. */
    #[Route('/{id}/reject', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function reject(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $this->commands->dispatch(new RejectQuotation($user->companyId(), $quotationId));

        return $this->present($user->companyId(), $quotationId);
    }

    /** Voids an emitted quotation: {reason}. No entry to reverse; the number is kept. 409 `quotation_not_open`. */
    #[Route('/{id}/void', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $in = $this->inputs->map($this->inputs->json($request), VoidInput::class);
        $this->commands->dispatch(new VoidQuotation($user->companyId(), $quotationId, $user->userId(), $in->reason));

        return $this->present($user->companyId(), $quotationId);
    }

    /**
     * "Convertir a factura" (§4.7): makes a draft sales invoice with the same client, contact, lines and taxes (dated
     * today, no formas de pago yet), once → the quotation, now `accepted`, whose `converted_invoice_id` is the draft
     * (its `quotation_id` is this quotation). 409 `quotation_already_converted`, `quotation_not_open`; 422
     * `validation_failed` when the invoice refuses what changed since (an inactive client, product or tax), by field path
     * (`tercero_id`, `lines.0.product_id`, `lines.1.charge_tax_id`): nothing is converted then.
     */
    #[Route('/{id}/convert', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class)]
    public function convert(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $this->commands->dispatch(new ConvertQuotation($user->companyId(), $quotationId, $user->userId()));

        return $this->present($user->companyId(), $quotationId);
    }

    /** A new draft, dated today, with the quotation's client, lines and texts (§4.15) → 201. */
    #[Route('/{id}/duplicate', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(QuotationOutput::class, status: 201)]
    public function duplicate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $copy = $this->commands->dispatch(new DuplicateQuotation($user->companyId(), $this->quotationId($id), $user->userId()));
        \assert($copy instanceof Uuid);

        return $this->present($user->companyId(), $copy, 201);
    }

    /** The PDF (§4.7), ANULADA when voided, BORRADOR on a draft. */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $document = $this->pdf->render($user->companyId(), $this->quotationId($id));

        return new Response($document->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $document->fileName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function emitting(string $id, SignedInUser $user, bool $send): JsonResponse
    {
        $quotationId = $this->quotationId($id);
        $this->commands->dispatch(new EmitQuotation($user->companyId(), $quotationId, $user->userId(), $send));

        return $this->present($user->companyId(), $quotationId);
    }

    private function quotationId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new QuotationNotFound();
    }

    private function present(Uuid $companyId, Uuid $id, int $status = 200): JsonResponse
    {
        $quotation = $this->quotations->get($companyId, $id);
        $labels = [];
        foreach ($quotation->lines() as $line) {
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
        $responsible = null;
        if (null !== $quotation->responsibleId()) {
            try {
                $responsible = $this->terceros->get($companyId, $quotation->responsibleId())->displayName;
            } catch (NotFound) {
                // Erased since (Ley 1581).
            }
        }

        return $this->json(QuotationOutput::of($quotation, $this->calendar->today(), $labels, $responsible), $status);
    }

    private function data(Request $request): QuotationData
    {
        $in = $this->inputs->map($this->inputs->json($request), QuotationInput::class);

        return new QuotationData(
            Uuid::fromString($in->terceroId),
            SalesInvoiceAccess::optionalUuid($in->contactId),
            SalesInvoiceAccess::optionalUuid($in->responsibleId),
            new \DateTimeImmutable($in->issueDate),
            null === $in->expiryDate || '' === $in->expiryDate ? null : new \DateTimeImmutable($in->expiryDate),
            $in->header,
            $in->terms,
            $in->notes,
            array_map(static fn (SalesInvoiceLineInput $l) => new SalesInvoiceLineData(
                SalesInvoiceAccess::optionalUuid($l->productId), $l->description, $l->quantity, $l->unitPrice, $l->discount,
                SalesInvoiceAccess::optionalUuid($l->chargeTaxId), SalesInvoiceAccess::optionalUuid($l->withholdingTaxId),
            ), $in->lines),
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
