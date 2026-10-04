<?php

namespace App\Purchasing\Application\Query;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Party\Application\Query\TerceroDirectory;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The company's internal record of a purchase (§4.15 "download PDF"): who sold it (with the supplier's NIT), the
 * supplier's number, the lines, the taxes and retenciones, the formas de pago; ANULADA across a voided one. A line
 * reads like on the sales invoice: its Valor total is subtotal plus impuesto, and the retención shows in the totals.
 */
final class PurchaseInvoicePdf
{
    public const TEMPLATE = 'pdf/purchase_invoice/purchase_invoice.html.twig';

    public function __construct(
        private readonly PurchaseInvoiceQueries $invoices,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
    ) {
    }

    /** @return array{bytes: string, fileName: string} */
    public function render(Uuid $companyId, Uuid $invoiceId): array
    {
        $context = $this->context($companyId, $invoiceId);
        $invoice = $context['invoice'];
        \assert($invoice instanceof PurchaseInvoiceView);

        return ['bytes' => $this->renderer->render(self::TEMPLATE, $context), 'fileName' => \sprintf('factura-compra-%s.pdf', $invoice->number ?? 'borrador')];
    }

    /**
     * What the template reads.
     *
     * @return array<string, mixed>
     */
    public function context(Uuid $companyId, Uuid $invoiceId): array
    {
        $invoice = $this->invoices->get($companyId, $invoiceId);
        $supplier = $this->terceros->get($companyId, Uuid::fromString($invoice->terceroId));

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Factura de compra',
            'number' => $invoice->number,
            'invoice' => $invoice,
            'supplier' => [
                'identification' => self::identification($supplier->identificationType, $supplier->identificationNumber, $supplier->checkDigit),
                'address' => trim(implode(', ', array_filter([$supplier->address, $supplier->city]))),
            ],
            'lines' => array_map(static fn (PurchaseInvoiceLineView $l) => [
                'label' => $l->productLabel ?? $l->accountLabel ?? '',
                'description' => $l->description,
                'quantity' => self::decimal($l->quantity),
                'unit_price' => $l->unitPrice,
                'discount' => self::decimal($l->discount),
                'charge_tax' => $l->chargeTaxName,
                'withholding_tax' => $l->withholdingTaxName,
                'total' => Money::of($l->subtotalAmount)->plus(Money::of($l->taxAmount))->toString(),
            ], $invoice->lines),
        ];
    }

    private function logo(Uuid $companyId): ?string
    {
        try {
            $file = $this->logos->logo($companyId);
            $bytes = file_get_contents($file->path);

            return false === $bytes ? null : 'data:'.$file->contentType.';base64,'.base64_encode($bytes);
        } catch (NotFound) {
            return null;
        }
    }

    /** "1.0000" → "1", "2.5000" → "2,5": as the sales invoice prints quantities. */
    private static function decimal(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }
        [$int, $dec] = explode('.', $value) + [1 => ''];

        return number_format((int) $int, 0, ',', '.').('' === $dec ? '' : ','.$dec);
    }

    private static function identification(string $type, string $number, ?string $checkDigit): string
    {
        $label = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'][$type] ?? strtoupper($type);

        return $label.' '.$number.(null === $checkDigit || '' === $checkDigit ? '' : '-'.$checkDigit);
    }
}
