<?php

namespace App\Purchasing\UI\Http\Controller;

use App\Purchasing\Application\Command\AttachSupplierFile;
use App\Purchasing\Application\Command\CreatePurchaseInvoice;
use App\Purchasing\Application\Command\DeletePurchaseInvoice;
use App\Purchasing\Application\Command\DuplicatePurchaseInvoice;
use App\Purchasing\Application\Command\EmitPurchaseInvoice;
use App\Purchasing\Application\Command\RemoveSupplierFile;
use App\Purchasing\Application\Command\UpdatePurchaseInvoice;
use App\Purchasing\Application\Command\VoidPurchaseInvoice;
use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Application\Query\PurchaseInvoiceFilter;
use App\Purchasing\Application\Query\PurchaseInvoicePdf;
use App\Purchasing\Application\Query\PurchaseInvoiceQueries;
use App\Purchasing\Domain\Error\SupplierFileTooLarge;
use App\Purchasing\Domain\Error\SupplierFileUnsupported;
use App\Purchasing\UI\Http\Input\PurchaseInvoiceInput;
use App\Purchasing\UI\Http\Input\VoidPurchaseInvoiceInput;
use App\Purchasing\UI\Http\Output\PurchaseInvoiceAttachmentOutput;
use App\Purchasing\UI\Http\Output\PurchaseInvoiceOutput;
use App\Purchasing\UI\Http\Output\PurchaseInvoiceSummaryOutput;
use App\Purchasing\UI\Http\PurchasingAccess;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Facturas de compra / gasto (§4.10, §4.12, §4.15). Every role reads; the owner and billing users write, emit and void
 * (§8). Another company's id is a 404 on every route.
 */
#[Route('/api/v1/purchase-invoices')]
final class PurchaseInvoiceController extends AbstractController
{
    private const FILE_MAX_BYTES = 10 * 1024 * 1024;
    private const FILE_TYPES = ['application/pdf' => 'application/pdf', 'text/xml' => 'application/xml', 'application/xml' => 'application/xml'];

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly PurchaseInvoiceQueries $invoices,
        private readonly SupplierFiles $files,
        private readonly PurchaseInvoicePdf $pdf,
        private readonly PurchasingAccess $access,
    ) {
    }

    /**
     * The list, newest first: ?q= (part of the internal number, the supplier's number or the supplier's name, matched
     * literally), ?status=draft|emitted|partially_paid|paid|voided, ?from and ?to (YYYY-MM-DD, the invoice date, both
     * included), ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(PurchaseInvoiceSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $page = $this->invoices->search($user->companyId(), new PurchaseInvoiceFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from')),
            self::date($q->getString('to')),
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ));

        return $this->json([
            'items' => array_map(PurchaseInvoiceSummaryOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /**
     * One invoice with its lines, formas de pago, payables and the supplier's files.
     */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(PurchaseInvoiceOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user, $this->access->invoiceId($id));
    }

    /**
     * Saves a draft: {tercero_id, supplier_invoice_number?, issue_date, due_date?, notes?, lines: [{product_id | account_id,
     * description, quantity, unit_price, discount?, charge_tax_id?, withholding_tax_id?}], payments: [{payment_method_id,
     * amount, due_date? (crédito; the invoice's when missing)}]} → 201. 422 `validation_failed` by field
     * (`lines[0].account_id`…), `duplicate_supplier_invoice_number` (violation on `supplier_invoice_number`).
     */
    #[Route('', methods: ['POST'])]
    #[ApiResponse(PurchaseInvoiceOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $input = $this->inputs->map($this->inputs->json($request), PurchaseInvoiceInput::class);
        $id = $this->commands->dispatch(new CreatePurchaseInvoice($user->companyId(), $user->userId(), $input->toContents()));
        \assert($id instanceof Uuid);

        return $this->present($user, $id, 201);
    }

    /**
     * Rewrites a draft with the same body as create. 409 `document_not_draft` once emitted.
     */
    #[Route('/{id}', methods: ['PUT'])]
    #[ApiResponse(PurchaseInvoiceOutput::class)]
    public function update(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $input = $this->inputs->map($this->inputs->json($request), PurchaseInvoiceInput::class);
        $this->commands->dispatch(new UpdatePurchaseInvoice($user->companyId(), $invoiceId, $input->toContents()));

        return $this->present($user, $invoiceId);
    }

    /**
     * Deletes a draft and its files → 204. 409 `document_not_draft` once emitted (void it instead).
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->access->mayWrite($user);
        $this->commands->dispatch(new DeletePurchaseInvoice($user->companyId(), $this->access->invoiceId($id)));

        return new Response(null, 204);
    }

    /**
     * Emits the draft: the internal number (FC), one payable per crédito line and the entry of Appendix A.3, together.
     * 409 `document_not_draft`, `period_locked`; 422 `payments_do_not_match_total`, `document_has_no_lines`,
     * `issue_date_in_future`, `supplier_inactive`, `validation_failed` on `supplier_invoice_number`.
     */
    #[Route('/{id}/emit', methods: ['POST'])]
    #[ApiResponse(PurchaseInvoiceOutput::class)]
    public function emit(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $this->commands->dispatch(new EmitPurchaseInvoice($user->companyId(), $invoiceId, $user->userId()));

        return $this->present($user, $invoiceId);
    }

    /**
     * Voids an emitted invoice: {reason}. Posts the reversing entry dated today, voids its payables, keeps the number.
     * 409 `document_has_allocations` (payments allocated: void them first), `document_not_emitted`, `document_voided`,
     * `period_locked`.
     */
    #[Route('/{id}/void', methods: ['POST'])]
    #[ApiResponse(PurchaseInvoiceOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $input = $this->inputs->map($this->inputs->json($request), VoidPurchaseInvoiceInput::class);
        $this->commands->dispatch(new VoidPurchaseInvoice($user->companyId(), $invoiceId, $user->userId(), $input->reason));

        return $this->present($user, $invoiceId);
    }

    /**
     * A new draft like this invoice, dated today, without the supplier's number → 201.
     */
    #[Route('/{id}/duplicate', methods: ['POST'])]
    #[ApiResponse(PurchaseInvoiceOutput::class, status: 201)]
    public function duplicate(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $copy = $this->commands->dispatch(new DuplicatePurchaseInvoice($user->companyId(), $this->access->invoiceId($id), $user->userId()));
        \assert($copy instanceof Uuid);

        return $this->present($user, $copy, 201);
    }

    /**
     * The company's record of the purchase as a PDF (ANULADA when voided).
     */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $pdf = $this->pdf->render($user->companyId(), $this->access->invoiceId($id));
        $response = new Response($pdf['bytes'], 200, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $pdf['fileName']));

        return $response;
    }

    /**
     * Attaches the supplier's invoice to a draft: multipart field `file`, a PDF or an XML judged by its content, 10 MB
     * at most → 201. 413 `attachment_too_large`; 415 `attachment_unsupported` (also a missing file); 409
     * `document_not_draft`.
     */
    #[Route('/{id}/attachments', methods: ['POST'])]
    #[ApiResponse(PurchaseInvoiceAttachmentOutput::class, status: 201)]
    public function attach(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->access->mayWrite($user);
        $invoiceId = $this->access->invoiceId($id);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new SupplierFileUnsupported();
        }
        if (\UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError() || ($file->isValid() && $file->getSize() > self::FILE_MAX_BYTES)) {
            throw new SupplierFileTooLarge();
        }
        if (!$file->isValid()) {
            throw new SupplierFileUnsupported();
        }
        $type = $this->contentTypeOf($file->getPathname());
        $attachmentId = $this->commands->dispatch(new AttachSupplierFile($user->companyId(), $invoiceId, $user->userId(), $file->getPathname(), $file->getClientOriginalName(), $type));
        \assert($attachmentId instanceof Uuid);

        foreach ($this->files->list($user->companyId(), $invoiceId) as $view) {
            if ($view->id === $attachmentId->toRfc4122()) {
                return $this->json(PurchaseInvoiceAttachmentOutput::of($view), 201);
            }
        }
        throw new \LogicException('The file just attached is listed.');
    }

    /**
     * Downloads one of the supplier's files (every role).
     */
    #[Route('/{id}/attachments/{attachmentId}', methods: ['GET'])]
    public function download(string $id, string $attachmentId, #[CurrentUser] SignedInUser $user): Response
    {
        $file = $this->files->find($user->companyId(), $this->access->invoiceId($id), $this->access->attachmentId($attachmentId));
        $response = new BinaryFileResponse($file->path, 200, ['Content-Type' => $file->contentType, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0, must-revalidate']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->fileName, 'archivo');

        return $response;
    }

    /**
     * Removes a file from a draft → 204. 409 `document_not_draft` once emitted.
     */
    #[Route('/{id}/attachments/{attachmentId}', methods: ['DELETE'])]
    public function removeAttachment(string $id, string $attachmentId, #[CurrentUser] SignedInUser $user): Response
    {
        $this->access->mayWrite($user);
        $this->commands->dispatch(new RemoveSupplierFile($user->companyId(), $this->access->invoiceId($id), $this->access->attachmentId($attachmentId)));

        return new Response(null, 204);
    }

    private function present(SignedInUser $user, Uuid $id, int $status = 200): JsonResponse
    {
        return $this->json(PurchaseInvoiceOutput::of($this->invoices->get($user->companyId(), $id)), $status);
    }

    /** PDF or XML by the bytes, never by the name or the type the browser claims. */
    private function contentTypeOf(string $path): string
    {
        $type = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);

        return false !== $type && isset(self::FILE_TYPES[$type]) ? self::FILE_TYPES[$type] : throw new SupplierFileUnsupported();
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date || $date->format('Y-m-d') !== $value ? throw ApiException::badRequest('invalid_date', 'Dates are YYYY-MM-DD.') : $date;
    }
}
