<?php

namespace App\Purchasing\Application\Document;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Application\Query\PurchaseInvoiceLineView;
use App\Purchasing\Application\Query\PurchaseOrderQueries;
use App\Purchasing\Application\Query\PurchaseOrderView;
use App\Shared\Application\Document\LineWithholdingView;
use App\Shared\Application\Document\WithholdingSummaryView;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The orden de compra's PDF (observations PRD §4.8), the document the supplier receives: the company's header and logo,
 * the supplier, the fechas, the lines with their Código, the totals with each retención and the observaciones. A draft
 * prints as BORRADOR, a voided one as ANULADA, a converted one says so.
 */
final class PurchaseOrderPdf
{
    public const TEMPLATE = 'pdf/purchase_order/purchase_order.html.twig';

    public function __construct(
        private readonly PurchaseOrderQueries $orders,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
    ) {
    }

    public function render(Uuid $companyId, Uuid $orderId): PurchaseOrderDocument
    {
        $context = $this->context($companyId, $orderId);
        $order = $context['order'];
        \assert($order instanceof PurchaseOrderView);

        return new PurchaseOrderDocument(
            $this->renderer->render(self::TEMPLATE, $context),
            \sprintf('orden-compra-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', $order->number ?? 'borrador')),
            $order->number ?? '',
            $context['company']->legalName,
            $order->terceroId,
            self::money(Money::of($order->netTotal)),
            null === $order->expectedDate ? '' : (new \DateTimeImmutable($order->expectedDate))->format('d/m/Y'),
        );
    }

    /**
     * What the template reads.
     *
     * @return array<string, mixed>
     */
    public function context(Uuid $companyId, Uuid $orderId): array
    {
        $order = $this->orders->get($companyId, $orderId);
        $supplier = $this->terceros->get($companyId, Uuid::fromString($order->terceroId));

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Orden de compra',
            'number' => $order->number,
            'order' => $order,
            'supplier' => [
                'identification' => self::identification($supplier->identificationType, $supplier->identificationNumber, $supplier->checkDigit),
                'address' => trim(implode(', ', array_filter([$supplier->address, $supplier->city]))),
                'email' => $supplier->email,
            ],
            'lines' => array_map(static fn (PurchaseInvoiceLineView $l) => [
                'label' => $l->productLabel ?? $l->accountLabel ?? '',
                'product_code' => $l->productCode ?? '',
                'description' => $l->description,
                'quantity' => self::decimal($l->quantity),
                'unit_price' => $l->unitPrice,
                'discount' => self::decimal($l->discount),
                'charge_tax' => $l->chargeTaxName,
                'withholding_tax' => [] === $l->withholdings ? $l->withholdingTaxName : implode(' · ', array_map(static fn (LineWithholdingView $w) => $w->name, $l->withholdings)),
                'total' => Money::of($l->subtotalAmount)->plus(Money::of($l->taxAmount))->toString(),
            ], $order->lines),
            'withholdings' => WithholdingSummaryView::of(array_map(static fn (PurchaseInvoiceLineView $l) => $l->withholdings, $order->lines)),
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

    private static function money(Money $amount): string
    {
        $negative = $amount->isNegative();
        [$int, $dec] = explode('.', ltrim($amount->toString(), '-'));

        return ($negative ? '-' : '').'$ '.number_format((int) $int, 0, ',', '.').','.$dec;
    }

    /** "1.0000" → "1", "2.5000" → "2,5". */
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
