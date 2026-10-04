<?php

namespace App\Tests\Functional\Sales;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What the quotation tests share, on top of the invoice fixtures: a quotation payload (an invoice's, no formas de pago,
 * plus its own fields), an employee, and drafts and emitted quotations made through the API.
 *
 * @mixin ApiTestCase
 */
trait QuotationFixtures
{
    use SalesInvoiceFixtures;

    /**
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    protected function quotationPayload(string $client, string $product, array $over = []): array
    {
        return $over + [
            'tercero_id' => $client,
            'contact_id' => null,
            'responsible_id' => null,
            'issue_date' => self::today(),
            'expiry_date' => null,
            'header' => null,
            'terms' => null,
            'notes' => null,
            'lines' => [$this->line($product)],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed> the QuotationOutput
     */
    protected function quotationDraft(array $payload): array
    {
        $quotation = $this->sendJson('POST', '/api/v1/quotations', $payload);
        self::assertResponseStatusCodeSame(201, 'The quotation draft is saved: '.json_encode($quotation));

        return $quotation;
    }

    /**
     * @return array<string, mixed> the emitted QuotationOutput
     */
    protected function emittedQuotation(?string $client = null, ?string $product = null): array
    {
        $client ??= $this->client();
        $draft = $this->quotationDraft($this->quotationPayload($client, $product ?? $this->service('Q'.random_int(1000, 9999))));
        $quotation = $this->sendJson('POST', '/api/v1/quotations/'.$draft['id'].'/emit', []);
        self::assertResponseIsSuccessful('Emitted: '.json_encode($quotation));

        return $quotation;
    }

    protected function employee(string $name = 'Elena Vendedora', string $number = '1010101010'): string
    {
        $tercero = $this->sendJson('POST', '/api/v1/terceros/quick', [
            'person_type' => 'persona',
            'identification_type' => 'cc',
            'identification_number' => $number,
            'first_names' => $name,
            'last_names' => 'Pérez',
            'email' => 'elena@acme.co',
            'roles' => ['empleado'],
        ]);
        self::assertResponseStatusCodeSame(201, 'An employee: '.json_encode($tercero));

        return $tercero['id'];
    }

    /** Moves the offer's fecha de vencimiento (the quotation is frozen once emitted, so the test reaches the table). */
    protected function expireOn(string $quotationId, string $date): void
    {
        $this->em()->getConnection()->update('quotation', ['expiry_date' => $date], ['id' => Uuid::fromString($quotationId)->toBinary()]);
        $this->em()->clear();
    }
}
