<?php

namespace App\Tests\Functional\Acceptance;

use App\Tests\Functional\Purchasing\PurchasingFixtures;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Observations PRD §8, OBS-7: "*Corregir* on an emitted, unpaid purchase invoice leaves it `voided` (reason recorded,
 * reversing entry today) and opens a draft with the same supplier number, lines and attachments; emitting the draft
 * does not trip the supplier-number uniqueness; *Corregir* on a paid invoice is refused with
 * `document_has_allocations`.".
 */
final class Obs07CorrectPurchaseInvoiceTest extends ApiTestCase
{
    use PurchasingFixtures;

    public function testCorregirVoidsReopensAndReEmitsUnderTheSameSupplierNumber(): void
    {
        $this->startPurchasing();
        $draft = $this->createDraft();
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        $this->client->request('POST', "/api/v1/purchase-invoices/{$draft['id']}/attachments", files: ['file' => new UploadedFile($path, 'factura-881.pdf', null, null, true)], server: ['HTTP_ACCEPT' => 'application/json']);
        $invoice = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);

        $copy = $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/correct", ['reason' => 'Valor mal digitado']);

        self::assertResponseStatusCodeSame(201);
        $voided = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame(['voided', 'Corregida: Valor mal digitado'], [$voided['status'], $voided['void_reason']], 'Voided, with the reason recorded.');
        [, $reversal] = $this->entries();
        self::assertSame(self::today(), $reversal->entryDate()->format('Y-m-d'), 'The reversing entry is dated today.');
        self::assertSame(['draft', 'FAC-881'], [$copy['status'], $copy['supplier_invoice_number']], 'A draft with the same supplier number…');
        self::assertSame(array_column($invoice['lines'], 'description'), array_column($copy['lines'], 'description'), '…the same lines…');
        self::assertSame(['factura-881.pdf'], array_column($copy['attachments'], 'file_name'), '…and the attachments.');

        $emitted = $this->sendJson('POST', "/api/v1/purchase-invoices/{$copy['id']}/emit", []);
        self::assertResponseIsSuccessful('Emitting the draft does not trip the supplier-number uniqueness: '.json_encode($emitted));
        self::assertSame(['emitted', 'FAC-881'], [$emitted['status'], $emitted['supplier_invoice_number']]);

        $this->db()->update('purchase_invoice', ['paid_amount' => $emitted['net_total'], 'status' => 'paid'], ['id' => Uuid::fromString($emitted['id'])->toBinary()]);
        $body = $this->sendJson('POST', "/api/v1/purchase-invoices/{$emitted['id']}/correct", ['reason' => 'Otra vez']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_has_allocations', $body['error'], 'Corregir on a paid invoice is refused.');
    }
}
