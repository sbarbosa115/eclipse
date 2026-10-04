<?php

namespace App\Shared\Application\Port;

/**
 * Turns a Twig template into PDF bytes: the PDF of a quotation, an invoice, a receipt, a report (§4.8 PDF, §4.15).
 * Templates live under templates/pdf/ and extend templates/pdf/document.html.twig (the company header, the page
 * frame); each context writes its own. Pure PHP (dompdf), so it runs on cPanel.
 */
interface PdfRenderer
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string;
}
