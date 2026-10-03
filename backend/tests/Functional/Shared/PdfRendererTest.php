<?php

namespace App\Tests\Functional\Shared;

use App\Shared\Infrastructure\Pdf\DompdfRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class PdfRendererTest extends KernelTestCase
{
    public function testATemplateBecomesAPdfWithTheCompanyHeader(): void
    {
        self::bootKernel();
        $renderer = new DompdfRenderer(static::getContainer()->get(Environment::class));

        $pdf = $renderer->render('pdf/test/sample.html.twig', [
            'company' => ['legalName' => 'Acme S.A.S.', 'tradeName' => null, 'identificationNumber' => '900123456', 'checkDigit' => '8', 'address' => 'Calle 1', 'city' => 'Bogotá', 'phone' => null, 'email' => null],
            'title' => 'Factura de venta',
            'number' => 'FV-1',
        ]);

        self::assertStringStartsWith('%PDF-', $pdf, 'dompdf answers PDF bytes.');
        self::assertGreaterThan(1000, \strlen($pdf));
    }
}
