<?php

namespace App\Tests\Functional\Purchasing;

use App\Purchasing\Domain\Model\Payable;
use App\Tests\Functional\Observations\WithholdingTaxes;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Corregir (observations PRD §4.6): one transaction voids the emitted invoice exactly as /void does and opens an
 * editable copy with the same supplier, supplier's number, dates, notes, lines, formas de pago and files; the two
 * point at each other. Refused like a void, and nothing is left half done when it is.
 */
final class CorrectPurchaseInvoiceApiTest extends ApiTestCase
{
    use PurchasingFixtures;
    use WithholdingTaxes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startPurchasing();
    }

    /** @return array<mixed> */
    private function upload(string $id, string $name, string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, $content);
        $this->client->request('POST', "/api/v1/purchase-invoices/$id/attachments", files: ['file' => new UploadedFile($path, $name, null, null, true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201, 'The file is attached.');

        return $this->body();
    }

    private static function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    /** @return array<mixed> the emitted invoice, with two retenciones on its line and the supplier's PDF */
    private function emittedWithAFile(): array
    {
        $lines = $this->payload()['lines'];
        $lines[0]['withholdings'] = self::withholdings($this->taxId('ReteFuente servicios 4 %', 'withholding'), $this->taxId('ReteIVA 15 %', 'withholding'));
        $draft = $this->createDraft(['lines' => $lines, 'payments' => [['payment_method_id' => $this->methodId('Crédito'), 'amount' => '1121500.00', 'due_date' => self::today(29)]]]);
        $this->upload($draft['id'], 'factura-881.pdf', self::pdfBytes());
        $emitted = $this->sendJson('POST', "/api/v1/purchase-invoices/{$draft['id']}/emit", []);
        self::assertResponseIsSuccessful('The invoice is emitted: '.json_encode($emitted));

        return $emitted;
    }

    /** @return array<mixed> */
    private function correct(string $id, string $reason = 'Valor mal digitado'): array
    {
        return $this->sendJson('POST', "/api/v1/purchase-invoices/$id/correct", ['reason' => $reason]);
    }

    /** @return list<Payable> */
    private function payables(string $invoiceId): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(Payable::class)->findBy(['companyId' => $this->company, 'invoiceId' => Uuid::fromString($invoiceId)]);
    }

    public function testCorrectingVoidsTheInvoiceAndOpensAnEditableCopy(): void
    {
        $invoice = $this->emittedWithAFile();

        $copy = $this->correct($invoice['id']);

        self::assertResponseStatusCodeSame(201, 'The answer is the new draft: '.json_encode($copy));
        self::assertNotSame($invoice['id'], $copy['id']);
        self::assertSame('draft', $copy['status']);
        self::assertNull($copy['number'], 'The copy takes its own internal number when it is emitted.');
        self::assertSame($invoice['id'], $copy['corrected_from_id'], 'The draft records what it corrects.');
        foreach (['tercero_id', 'supplier_invoice_number', 'issue_date', 'due_date', 'notes', 'net_total', 'withholding_total'] as $field) {
            self::assertSame($invoice[$field], $copy[$field], "The copy keeps $field: the point is to fix one detail, not to retype the invoice.");
        }
        $line = static fn (array $l) => [$l['account_id'], $l['description'], $l['quantity'], $l['unit_price'], $l['charge_tax_id'], array_column($l['withholdings'], 'tax_id'), array_column($l['withholdings'], 'amount')];
        self::assertSame(array_map($line, $invoice['lines']), array_map($line, $copy['lines']), 'Every line with every retención.');
        self::assertCount(2, $copy['lines'][0]['withholdings']);
        $payment = static fn (array $p) => [$p['payment_method_id'], $p['amount'], $p['due_date']];
        self::assertSame(array_map($payment, $invoice['payments']), array_map($payment, $copy['payments']), 'The formas de pago as they were, due dates included.');
        self::assertSame(['factura-881.pdf'], array_column($copy['attachments'], 'file_name'), 'The supplier\'s file is copied to the draft.');
        self::assertNotSame($invoice['attachments'][0]['id'], $copy['attachments'][0]['id'], 'A copy of its own: removing it from the draft leaves the voided invoice\'s.');

        $original = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame('voided', $original['status']);
        self::assertSame('FC-1', $original['number'], 'The number is kept and never reused.');
        self::assertSame('Corregida: Valor mal digitado', $original['void_reason']);
        self::assertSame($copy['id'], $original['corrected_by_id'], 'The voided invoice points at its replacement.');
        self::assertSame(['factura-881.pdf'], array_column($original['attachments'], 'file_name'), 'The voided invoice keeps its own file.');
        [$entry, $reversal] = $this->entries();
        self::assertTrue($entry->id()->equals($reversal->reversesId() ?? Uuid::v7()), 'The void posted the reversing entry…');
        self::assertSame(self::today(), $reversal->entryDate()->format('Y-m-d'), '…dated today.');
        self::assertCount(2, $this->entries(), 'The draft posts nothing until it is emitted.');
        self::assertTrue($this->payables($invoice['id'])[0]->isVoided(), 'Nothing is owed on the voided invoice.');

        $this->client->request('GET', "/api/v1/purchase-invoices/{$copy['id']}/attachments/{$copy['attachments'][0]['id']}");
        self::assertResponseIsSuccessful();
        self::assertSame(self::pdfBytes(), (string) file_get_contents($this->client->getResponse()->getFile()->getPathname()), 'The bytes are copied too.');
    }

    public function testTheCopyIsEmittedAgainUnderTheSameSupplierNumber(): void
    {
        $invoice = $this->emitInvoice();
        $copy = $this->correct($invoice['id']);

        $other = $this->sendJson('POST', '/api/v1/purchase-invoices', $this->payload());
        self::assertResponseStatusCodeSame(422, 'The draft holds the supplier\'s number now: '.json_encode($other));
        self::assertSame('duplicate_supplier_invoice_number', $other['error']);

        $fixed = $this->payload(['notes' => 'Mantenimiento de octubre (corregida)']);
        $this->sendJson('PUT', "/api/v1/purchase-invoices/{$copy['id']}", $fixed);
        self::assertResponseIsSuccessful('The copy is edited with its own number: the voided invoice does not hold it.');
        $emitted = $this->sendJson('POST', "/api/v1/purchase-invoices/{$copy['id']}/emit", []);

        self::assertResponseIsSuccessful('Emitting the copy does not trip the supplier-number uniqueness: '.json_encode($emitted));
        self::assertSame(['emitted', 'FC-2', 'FAC-881'], [$emitted['status'], $emitted['number'], $emitted['supplier_invoice_number']]);
        self::assertSame($invoice['id'], $emitted['corrected_from_id']);
        self::assertCount(3, $this->entries(), 'The original, its reversal and the corrected invoice\'s entry.');
    }

    public function testAnInvoiceWithAPaymentAllocatedIsRefusedAndNothingChanges(): void
    {
        $invoice = $this->emitInvoice();
        $payable = $this->payables($invoice['id'])[0];
        $this->db()->update('payable', ['balance' => '1000000.00'], ['id' => $payable->id()->toBinary()]);
        $this->db()->update('purchase_invoice', ['paid_amount' => '150000.00', 'status' => 'partially_paid'], ['id' => Uuid::fromString($invoice['id'])->toBinary()]);

        $body = $this->correct($invoice['id']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_has_allocations', $body['error'], 'Void the supplier payment first.');
        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total'], 'No copy is left behind.');
        self::assertCount(1, $this->entries(), 'No reversing entry.');
    }

    public function testOnlyAnEmittedInvoiceThatIsNotVoidedIsCorrected(): void
    {
        $draft = $this->createDraft(['supplier_invoice_number' => 'FAC-1']);
        $body = $this->correct($draft['id']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_not_emitted', $body['error'], 'A draft is edited, not corrected.');

        $invoice = $this->emitInvoice();
        $this->sendJson('POST', "/api/v1/purchase-invoices/{$invoice['id']}/void", ['reason' => 'Registrada dos veces']);
        $body = $this->correct($invoice['id']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('document_voided', $body['error']);

        $copy = $this->emitInvoice(['supplier_invoice_number' => 'FAC-2']);
        $this->correct($copy['id']);
        self::assertResponseStatusCodeSame(201);
        $body = $this->correct($copy['id']);
        self::assertResponseStatusCodeSame(409, 'A second Corregir on the same invoice finds it voided.');
        self::assertSame('document_voided', $body['error']);
    }

    public function testALockedPeriodRefusesItAndRollsEverythingBack(): void
    {
        $invoice = $this->emittedWithAFile();
        $this->lockBooks(self::today());

        $body = $this->correct($invoice['id']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('period_locked', $body['error'], 'The reversing entry would fall in a closed period.');
        $after = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame(['emitted', null], [$after['status'], $after['corrected_by_id']], 'The invoice is untouched.');
        self::assertSame(1, $this->getJson('/api/v1/purchase-invoices')['total'], 'No copy is left behind.');
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM attachment WHERE owner_type = 'purchase_invoice'"), 'No copied file is recorded.');
    }

    public function testACorrectionNeedsAReason(): void
    {
        $invoice = $this->emitInvoice();

        $body = $this->correct($invoice['id'], ' ');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['reason'], self::violationFields($body));
        self::assertSame('emitted', $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}")['status']);
    }

    public function testTheListShowsWhichInvoiceCorrectedAVoidedOne(): void
    {
        $invoice = $this->emitInvoice();
        $copy = $this->correct($invoice['id']);
        self::assertResponseStatusCodeSame(201);

        $rows = array_column($this->getJson('/api/v1/purchase-invoices')['items'], null, 'id');

        self::assertSame($copy['id'], $rows[$invoice['id']]['corrected_by_id'], 'The voided row links to its replacement (the "Corregida" hint).');
        self::assertNull($rows[$copy['id']]['corrected_by_id']);
    }

    public function testDeletingTheCopyForgetsTheLink(): void
    {
        $invoice = $this->emittedWithAFile();
        $copy = $this->correct($invoice['id']);

        $this->client->request('DELETE', "/api/v1/purchase-invoices/{$copy['id']}");

        self::assertResponseStatusCodeSame(204);
        $original = $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}");
        self::assertSame(['voided', null], [$original['status'], $original['corrected_by_id']], 'The voided invoice no longer points at a draft that is gone.');
        self::assertSame(['factura-881.pdf'], array_column($original['attachments'], 'file_name'), 'Deleting the copy\'s file leaves the original\'s.');
        $this->client->request('GET', "/api/v1/purchase-invoices/{$invoice['id']}/attachments/{$original['attachments'][0]['id']}");
        self::assertResponseIsSuccessful('Its bytes are still there.');
    }

    public function testTheBillingUserAndTheAccountantCorrect(): void
    {
        $first = $this->emitInvoice(['supplier_invoice_number' => 'FAC-1']);
        $second = $this->emitInvoice(['supplier_invoice_number' => 'FAC-2']);

        $this->signInAs('billing');
        $this->correct($first['id']);
        self::assertResponseStatusCodeSame(201, 'WRITE_DOCUMENTS (§8).');
        $this->signInAs('accountant');
        $this->correct($second['id']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testAnotherCompanysInvoiceIsNotFound(): void
    {
        $invoice = $this->emitInvoice();
        $this->signOut();
        $this->startPurchasing('bea@otra.co', '900765432', 'Otra S.A.S.');

        $this->correct($invoice['id']);
        self::assertResponseStatusCodeSame(404, 'Another company\'s id is not found.');
        $this->correct('not-an-id');
        self::assertResponseStatusCodeSame(404);

        $this->signOut();
        $this->signIn('ana@acme.co');
        self::assertSame('emitted', $this->getJson("/api/v1/purchase-invoices/{$invoice['id']}")['status'], 'Nothing happened to it.');
    }
}
