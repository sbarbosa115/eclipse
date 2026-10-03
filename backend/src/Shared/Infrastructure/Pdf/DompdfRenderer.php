<?php

namespace App\Shared\Infrastructure\Pdf;

use App\Shared\Application\Port\PdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PDFs with dompdf: no remote resources (a logo arrives as a data: URI in the context), no PHP in templates, Letter
 * size as Colombian invoices are printed.
 */
final class DompdfRenderer implements PdfRenderer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $template, array $context): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render($template, $context), 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
