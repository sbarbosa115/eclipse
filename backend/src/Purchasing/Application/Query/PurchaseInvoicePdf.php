<?php

namespace App\Purchasing\Application\Query;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/**
 * The company's internal record of a purchase (§4.15 "download PDF"): who sold it, the supplier's number, the lines,
 * the taxes and retenciones, the formas de pago; ANULADA across a voided one.
 */
final class PurchaseInvoicePdf
{
    public function __construct(
        private readonly PurchaseInvoiceQueries $invoices,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly PdfRenderer $renderer,
    ) {
    }

    /** @return array{bytes: string, fileName: string} */
    public function render(Uuid $companyId, Uuid $invoiceId): array
    {
        $invoice = $this->invoices->get($companyId, $invoiceId);
        $logo = null;
        try {
            $file = $this->logos->logo($companyId);
            $bytes = file_get_contents($file->path);
            $logo = false === $bytes ? null : 'data:'.$file->contentType.';base64,'.base64_encode($bytes);
        } catch (NotFound) {
        }

        $bytes = $this->renderer->render('pdf/purchase_invoice/purchase_invoice.html.twig', [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $logo,
            'title' => 'Factura de compra',
            'number' => $invoice->number,
            'invoice' => $invoice,
        ]);

        return ['bytes' => $bytes, 'fileName' => \sprintf('factura-compra-%s.pdf', $invoice->number ?? 'borrador')];
    }
}
