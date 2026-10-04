<?php

namespace App\Purchasing\Application\Document;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Purchasing\Domain\Model\SupplierPaymentAllocation;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\ReceiptStatus;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The recibo de pago's PDF (§4.11): the company's header and logo, the number, the supplier's fiscal data, the date,
 * where the money went out, the invoices it paid and the amount; ANULADA across a voided one, with the reason (§4.12).
 */
final class SupplierPaymentPdf
{
    public const TEMPLATE = 'pdf/supplier_payment/payment.html.twig';

    public function __construct(
        private readonly SupplierPaymentRepository $payments,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
    ) {
    }

    public function render(Uuid $companyId, Uuid $paymentId): SupplierPaymentDocument
    {
        $payment = $this->payments->get($companyId, $paymentId);
        $context = $this->context($payment);

        return new SupplierPaymentDocument(
            $this->renderer->render(self::TEMPLATE, $context),
            \sprintf('recibo-de-pago-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', $payment->number())),
            $payment->number(),
            $context['company']->legalName,
            $payment->terceroId()->toRfc4122(),
            self::money($payment->amount()),
        );
    }

    /**
     * What the template reads, everything already printed as text.
     *
     * @return array<string, mixed>
     */
    public function context(SupplierPayment $payment): array
    {
        $companyId = $payment->companyId();
        $supplier = $this->terceros->get($companyId, $payment->terceroId());

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Recibo de pago',
            'number' => $payment->number(),
            'voided' => ReceiptStatus::Voided === $payment->status(),
            'payment' => [
                'date' => self::date($payment->receiptDate()),
                'method' => $payment->methodName(),
                'amount' => self::money($payment->amount()),
                'notes' => $payment->notes(),
                'void_reason' => $payment->voidReason(),
                'voided_at' => self::date($payment->voidedAt()),
            ],
            'supplier' => [
                'name' => $supplier->displayName,
                'identification' => self::identification($supplier->identificationType, $supplier->identificationNumber, $supplier->checkDigit),
                'address' => trim(implode(', ', array_filter([$supplier->address, $supplier->city]))),
                'email' => $supplier->email,
            ],
            'allocations' => array_map(static fn (SupplierPaymentAllocation $a) => [
                'invoice' => $a->invoiceNumber(),
                'amount' => self::money($a->amount()),
            ], $payment->allocations()),
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

    /** $ 1.190.000,00 */
    private static function money(Money $amount): string
    {
        $negative = $amount->isNegative();
        [$int, $dec] = explode('.', ltrim($amount->toString(), '-'));

        return ($negative ? '-' : '').'$ '.number_format((int) $int, 0, ',', '.').','.$dec;
    }

    private static function date(?\DateTimeImmutable $date): string
    {
        return null === $date ? '' : $date->format('d/m/Y');
    }

    private static function identification(string $type, string $number, ?string $checkDigit): string
    {
        $label = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'][$type] ?? strtoupper($type);

        return $label.' '.$number.(null === $checkDigit || '' === $checkDigit ? '' : '-'.$checkDigit);
    }
}
