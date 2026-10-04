<?php

namespace App\Sales\Application\Document;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Model\CashReceiptAllocation;
use App\Sales\Domain\Repository\CashReceiptRepository;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\ReceiptStatus;
use Symfony\Component\Uid\Uuid;

/**
 * The recibo de caja's PDF (§4.9): the company's header and logo, the number, the client's fiscal data, the date, where
 * the money came in, the invoices it paid and the amount; ANULADA across a voided one, with the reason (§4.12).
 */
final class CashReceiptPdf
{
    public const TEMPLATE = 'pdf/cash_receipt/receipt.html.twig';

    public function __construct(
        private readonly CashReceiptRepository $receipts,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
    ) {
    }

    public function render(Uuid $companyId, Uuid $receiptId): CashReceiptDocument
    {
        $receipt = $this->receipts->get($companyId, $receiptId);
        $context = $this->context($receipt);

        return new CashReceiptDocument(
            $this->renderer->render(self::TEMPLATE, $context),
            \sprintf('recibo-de-caja-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', $receipt->number())),
            $receipt->number(),
            $context['company']->legalName,
            $receipt->terceroId()->toRfc4122(),
            Printed::money($receipt->amount()),
        );
    }

    /**
     * What the template reads, everything already printed as text.
     *
     * @return array<string, mixed>
     */
    public function context(CashReceipt $receipt): array
    {
        $companyId = $receipt->companyId();
        $client = $this->terceros->get($companyId, $receipt->terceroId());

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Recibo de caja',
            'number' => $receipt->number(),
            'voided' => ReceiptStatus::Voided === $receipt->status(),
            'receipt' => [
                'date' => Printed::date($receipt->receiptDate()),
                'method' => $receipt->methodName(),
                'amount' => Printed::money($receipt->amount()),
                'notes' => $receipt->notes(),
                'void_reason' => $receipt->voidReason(),
                'voided_at' => Printed::date($receipt->voidedAt()),
            ],
            'client' => [
                'name' => $client->displayName,
                'identification' => self::identification($client->identificationType, $client->identificationNumber, $client->checkDigit),
                'address' => trim(implode(', ', array_filter([$client->address, $client->city]))),
                'email' => $client->email,
            ],
            'allocations' => array_map(static fn (CashReceiptAllocation $a) => [
                'invoice' => $a->invoiceNumber(),
                'amount' => Printed::money($a->amount()),
            ], $receipt->allocations()),
        ];
    }

    private function logo(Uuid $companyId): ?string
    {
        try {
            $logo = $this->logos->logo($companyId);
        } catch (NotFound) {
            return null;
        }
        $bytes = @file_get_contents($logo->path);

        return false === $bytes ? null : 'data:'.$logo->contentType.';base64,'.base64_encode($bytes);
    }

    private static function identification(string $type, string $number, ?string $checkDigit): string
    {
        $label = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'][$type] ?? strtoupper($type);

        return $label.' '.$number.(null === $checkDigit || '' === $checkDigit ? '' : '-'.$checkDigit);
    }
}
