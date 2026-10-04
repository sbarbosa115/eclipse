<?php

namespace App\Sales\UI\Http\Controller;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Command\CashReceiptAllocationData;
use App\Sales\Application\Command\ReceiveCash;
use App\Sales\Application\Command\SendCashReceipt;
use App\Sales\Application\Command\VoidCashReceipt;
use App\Sales\Application\Document\CashReceiptPdf;
use App\Sales\Application\Query\CashReceiptFilter;
use App\Sales\Application\Query\CashReceiptQueries;
use App\Sales\Domain\Error\CashReceiptNotFound;
use App\Sales\UI\Http\Input\CashReceiptAllocationInput;
use App\Sales\UI\Http\Input\CashReceiptInput;
use App\Sales\UI\Http\Input\VoidInput;
use App\Sales\UI\Http\Output\CashReceiptOutput;
use App\Sales\UI\Http\Output\CashReceiptSummaryOutput;
use App\Sales\UI\Http\Output\OpenReceivableOutput;
use App\Shared\Application\Command\CommandBus;
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
 * Recibos de caja (§4.9, §4.12, §4.15). Every role reads (and downloads the PDF); the owner and billing users
 * receive, send and void (§8: the accountant cannot emit commercial documents). The voter decides
 * (Permission::READ_DOCUMENTS / WRITE_DOCUMENTS).
 */
#[Route('/api/v1/cash-receipts')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class CashReceiptController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly CashReceiptQueries $receipts,
        private readonly TerceroDirectory $terceros,
        private readonly CashReceiptPdf $pdf,
    ) {
    }

    /**
     * The list, newest first: ?q= (part of the number or the client's name, matched literally), ?status=emitted|voided,
     * ?from=, ?to= (the receipt's date, YYYY-MM-DD, both included), ?tercero_id=, ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(CashReceiptSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $page = $this->receipts->search($user->companyId(), new CashReceiptFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from'), 'from'),
            self::date($q->getString('to'), 'to'),
            $q->getString('tercero_id') ?: null,
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ));

        return $this->json([
            'items' => array_map(CashReceiptSummaryOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /**
     * What a client still owes (§4.9), to allocate a receipt: ?tercero_id= (required). Its receivables with a balance,
     * the oldest due first. 404 for a client the company does not have.
     */
    #[Route('/open-receivables', methods: ['GET'])]
    #[ApiResponse(OpenReceivableOutput::class, key: 'items', list: true)]
    public function openReceivables(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $id = $request->query->getString('tercero_id');
        if (!Uuid::isValid($id)) {
            throw ApiException::badRequest('tercero_id_required', '"tercero_id" is the client\'s id.');
        }
        $client = $this->terceros->get($user->companyId(), Uuid::fromString($id));

        return $this->json(['items' => array_map(OpenReceivableOutput::of(...), $this->receipts->openReceivables($user->companyId(), Uuid::fromString($client->id)))]);
    }

    /** One receipt, whole. */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(CashReceiptOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user->companyId(), self::receiptId($id));
    }

    /**
     * Saves and so emits a receipt (§4.9): {tercero_id, receipt_date, payment_method_id (an active contado method),
     * amount, notes?, allocations: [{receivable_id, amount}], send?} → 201. The RC number, each invoice collected, the
     * A.2 entry; with `send: true` (*Guardar y enviar por mail*) the PDF is then e-mailed to the client. 422
     * `validation_failed` by field (`allocations.0.receivable_id` for an unknown or another client's receivable),
     * `allocation_exceeds_balance`, `allocations_do_not_match_amount` (detail: both sums), `tercero_has_no_email`;
     * 409 `period_locked`.
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(CashReceiptOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $in = $this->inputs->map($this->inputs->json($request), CashReceiptInput::class);
        $id = $this->commands->dispatch(new ReceiveCash(
            $user->companyId(),
            $user->userId(),
            Uuid::fromString($in->terceroId),
            new \DateTimeImmutable($in->receiptDate),
            Uuid::fromString($in->paymentMethodId),
            $in->amount,
            $in->notes,
            array_map(static fn (CashReceiptAllocationInput $a) => new CashReceiptAllocationData(Uuid::fromString($a->receivableId), $a->amount), $in->allocations),
            $in->send,
        ));
        \assert($id instanceof Uuid);

        return $this->present($user->companyId(), $id, 201);
    }

    /**
     * Voids the receipt today (§4.12): {reason}. Each invoice is owed again, the reversing entry is posted, the number
     * is kept. 409 `document_voided`, `period_locked`.
     */
    #[Route('/{id}/void', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(CashReceiptOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $receiptId = self::receiptId($id);
        $in = $this->inputs->map($this->inputs->json($request), VoidInput::class);
        $this->commands->dispatch(new VoidCashReceipt($user->companyId(), $receiptId, $user->userId(), $in->reason));

        return $this->present($user->companyId(), $receiptId);
    }

    /** E-mails the receipt's PDF to the client again → 202. 409 `document_not_emitted` (voided); 422 `tercero_has_no_email`. */
    #[Route('/{id}/send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    public function send(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->commands->dispatch(new SendCashReceipt($user->companyId(), self::receiptId($id)));

        return new Response(null, 202);
    }

    /** The PDF (§4.9), ANULADA when voided. */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $document = $this->pdf->render($user->companyId(), self::receiptId($id));

        return new Response($document->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $document->fileName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function present(Uuid $companyId, Uuid $id, int $status = 200): JsonResponse
    {
        return $this->json(CashReceiptOutput::of($this->receipts->get($companyId, $id)), $status);
    }

    private static function receiptId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new CashReceiptNotFound();
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
