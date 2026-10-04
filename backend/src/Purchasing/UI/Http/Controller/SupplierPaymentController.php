<?php

namespace App\Purchasing\UI\Http\Controller;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Application\Command\PaySupplier;
use App\Purchasing\Application\Command\SendSupplierPayment;
use App\Purchasing\Application\Command\SupplierPaymentAllocationData;
use App\Purchasing\Application\Command\VoidSupplierPayment;
use App\Purchasing\Application\Document\SupplierPaymentPdf;
use App\Purchasing\Application\Query\PayableQueries;
use App\Purchasing\Application\Query\SupplierPaymentFilter;
use App\Purchasing\Application\Query\SupplierPaymentQueries;
use App\Purchasing\Domain\Error\SupplierPaymentNotFound;
use App\Purchasing\UI\Http\Input\SupplierPaymentAllocationInput;
use App\Purchasing\UI\Http\Input\SupplierPaymentInput;
use App\Purchasing\UI\Http\Input\VoidSupplierPaymentInput;
use App\Purchasing\UI\Http\Output\OpenPayableOutput;
use App\Purchasing\UI\Http\Output\SupplierPaymentOutput;
use App\Purchasing\UI\Http\Output\SupplierPaymentSummaryOutput;
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
 * Recibos de pago (§4.11, §4.12, §4.15). Every role reads (and downloads the PDF); the owner and billing users
 * pay, send and void (§8: the accountant cannot emit commercial documents). The voter decides
 * (Permission::READ_DOCUMENTS / WRITE_DOCUMENTS).
 */
#[Route('/api/v1/supplier-payments')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class SupplierPaymentController extends AbstractController
{
    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly SupplierPaymentQueries $payments,
        private readonly PayableQueries $payables,
        private readonly TerceroDirectory $terceros,
        private readonly SupplierPaymentPdf $pdf,
    ) {
    }

    /**
     * The list, newest first: ?q= (part of the number or the supplier's name, matched literally), ?status=emitted|voided,
     * ?from=, ?to= (the payment's date, YYYY-MM-DD, both included), ?tercero_id=, ?page, ?per_page ≤ 100.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(SupplierPaymentSummaryOutput::class, page: true)]
    public function list(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $q = $request->query;
        $page = $this->payments->search($user->companyId(), new SupplierPaymentFilter(
            $q->getString('q') ?: null,
            $q->getString('status') ?: null,
            self::date($q->getString('from'), 'from'),
            self::date($q->getString('to'), 'to'),
            $q->getString('tercero_id') ?: null,
            max(1, $q->getInt('page', 1)),
            $q->getInt('per_page', 25),
        ));

        return $this->json([
            'items' => array_map(SupplierPaymentSummaryOutput::of(...), $page->items),
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
        ]);
    }

    /**
     * What a supplier still owes (§4.11), to allocate a payment: ?tercero_id= (required). Its payables with a balance,
     * the oldest due first. 404 for a supplier the company does not have.
     */
    #[Route('/open-payables', methods: ['GET'])]
    #[ApiResponse(OpenPayableOutput::class, key: 'items', list: true)]
    public function openPayables(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $id = $request->query->getString('tercero_id');
        if (!Uuid::isValid($id)) {
            throw ApiException::badRequest('tercero_id_required', '"tercero_id" is the supplier\'s id.');
        }
        $supplier = $this->terceros->get($user->companyId(), Uuid::fromString($id));

        return $this->json(['items' => array_map(OpenPayableOutput::of(...), $this->payables->openFor($user->companyId(), Uuid::fromString($supplier->id)))]);
    }

    /** One payment, whole. */
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponse(SupplierPaymentOutput::class)]
    public function show(string $id, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->present($user->companyId(), self::paymentId($id));
    }

    /**
     * Saves and so emits a payment (§4.11): {tercero_id, receipt_date, payment_method_id (an active contado method),
     * amount, notes?, allocations: [{payable_id, amount}], send?} → 201. The RP number, each invoice paid, the
     * A.4 entry; with `send: true` (*Guardar y enviar*) the PDF is then e-mailed to the supplier. 422
     * `validation_failed` by field (`allocations.0.payable_id` for an unknown or another supplier's payable),
     * `allocation_exceeds_balance`, `allocations_do_not_match_amount` (detail: both sums), `tercero_has_no_email`;
     * 409 `period_locked`.
     */
    #[Route('', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(SupplierPaymentOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $in = $this->inputs->map($this->inputs->json($request), SupplierPaymentInput::class);
        $id = $this->commands->dispatch(new PaySupplier(
            $user->companyId(),
            $user->userId(),
            Uuid::fromString($in->terceroId),
            new \DateTimeImmutable($in->receiptDate),
            Uuid::fromString($in->paymentMethodId),
            $in->amount,
            $in->notes,
            array_map(static fn (SupplierPaymentAllocationInput $a) => new SupplierPaymentAllocationData(Uuid::fromString($a->payableId), $a->amount), $in->allocations),
            $in->send,
        ));
        \assert($id instanceof Uuid);

        return $this->present($user->companyId(), $id, 201);
    }

    /**
     * Voids the payment today (§4.12): {reason}. Each invoice is owed again, the reversing entry is posted, the number
     * is kept. 409 `document_voided`, `period_locked`.
     */
    #[Route('/{id}/void', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    #[ApiResponse(SupplierPaymentOutput::class)]
    public function void(string $id, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $paymentId = self::paymentId($id);
        $in = $this->inputs->map($this->inputs->json($request), VoidSupplierPaymentInput::class);
        $this->commands->dispatch(new VoidSupplierPayment($user->companyId(), $paymentId, $user->userId(), $in->reason));

        return $this->present($user->companyId(), $paymentId);
    }

    /** E-mails the payment's PDF to the supplier again → 202. 409 `document_not_emitted` (voided); 422 `tercero_has_no_email`. */
    #[Route('/{id}/send', methods: ['POST'])]
    #[IsGranted(Permission::WRITE_DOCUMENTS)]
    public function send(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $this->commands->dispatch(new SendSupplierPayment($user->companyId(), self::paymentId($id)));

        return new Response(null, 202);
    }

    /** The PDF (§4.11), ANULADA when voided. */
    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(string $id, #[CurrentUser] SignedInUser $user): Response
    {
        $document = $this->pdf->render($user->companyId(), self::paymentId($id));

        return new Response($document->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $document->fileName),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function present(Uuid $companyId, Uuid $id, int $status = 200): JsonResponse
    {
        return $this->json(SupplierPaymentOutput::of($this->payments->get($companyId, $id)), $status);
    }

    private static function paymentId(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw new SupplierPaymentNotFound();
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
