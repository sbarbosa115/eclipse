<?php

namespace App\Tests\Functional\Company;

use App\Access\Domain\Model\Role;
use App\Tests\Functional\Ledger\CatalogTestCase;

final class NumberingApiTest extends CatalogTestCase
{
    /** @return array<string, array<string, mixed>> the series by kind */
    private function series(): array
    {
        return array_column($this->getJson('/api/v1/company/numbering')['items'], null, 'kind');
    }

    public function testTheOwnerSeesTheFiveDocumentSeriesStartingAtOne(): void
    {
        $this->signUpOwner();

        $series = $this->series();

        self::assertSame(['quotation', 'cash_receipt', 'purchase_invoice', 'supplier_payment', 'sales_invoice_internal'], array_keys($series), 'The journal\'s own series is not offered.');
        self::assertSame(['C', 'RC', 'FC', 'RP', 'FVI'], array_column($series, 'prefix'));
        self::assertSame([1, 1, 1, 1, 1], array_column($series, 'next_number'));
    }

    public function testTheOwnerMovesAPrefixAndTheNextNumberForward(): void
    {
        $companyId = $this->signUpOwner();

        $saved = $this->sendJson('PUT', '/api/v1/company/numbering/cash_receipt', ['prefix' => 'rcb', 'next_number' => 250]);

        self::assertResponseIsSuccessful();
        self::assertSame(['cash_receipt', 'RCB', 250], [$saved['kind'], $saved['prefix'], $saved['next_number']]);
        self::assertSame(250, $this->series()['cash_receipt']['next_number']);
        $log = $this->audit($companyId, 'numbering_series.updated');
        self::assertSame(['RC', 1, 'RCB', 250], [$log[0]->data()['from']['prefix'], $log[0]->data()['from']['next_number'], $log[0]->data()['to']['prefix'], $log[0]->data()['to']['next_number']]);
    }

    public function testTheNextNumberCannotGoBelowTheCurrentOne(): void
    {
        $this->signUpOwner();
        $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'C', 'next_number' => 40]);

        $body = $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'C', 'next_number' => 39]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('next_number', $body['violations'][0]['field']);
        self::assertSame('El próximo número no puede ser menor que el actual.', $body['violations'][0]['message']);
        self::assertSame(40, $this->series()['quotation']['next_number'], 'A refusal changes nothing.');
        $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'COT', 'next_number' => 40]);
        self::assertResponseIsSuccessful('The same number with another prefix is fine.');
    }

    public function testThePrefixIsValidated(): void
    {
        $this->signUpOwner();

        $body = $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'C-1', 'next_number' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('prefix', $body['violations'][0]['field']);
    }

    public function testTheJournalsSeriesAndUnknownKindsAreNotFound(): void
    {
        $this->signUpOwner();

        $this->sendJson('PUT', '/api/v1/company/numbering/journal_entry', ['prefix' => 'X', 'next_number' => 999]);
        self::assertResponseStatusCodeSame(404, 'The journal numbers its own entries.');
        $this->sendJson('PUT', '/api/v1/company/numbering/nope', ['prefix' => 'X', 'next_number' => 9]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOthersReadButOnlyTheOwnerEdits(): void
    {
        $companyId = $this->signUpOwner();

        foreach ([Role::Accountant, Role::Billing] as $role) {
            $this->signInAs($role, $companyId);
            self::assertCount(5, $this->getJson('/api/v1/company/numbering')['items'], "$role->value reads.");
            $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'X', 'next_number' => 5]);
            self::assertResponseStatusCodeSame(403, "$role->value cannot edit.");
        }
        self::assertSame('C', $this->series()['quotation']['prefix']);
    }

    public function testEachCompanyHasItsOwnSeries(): void
    {
        $this->signUpOwner('ana@a.co', '900123456', 'A');
        $this->sendJson('PUT', '/api/v1/company/numbering/quotation', ['prefix' => 'AAA', 'next_number' => 77]);
        $this->signOut();
        $this->signUpOwner('beto@b.co', '800197268', 'B');

        self::assertSame(['C', 1], [$this->series()['quotation']['prefix'], $this->series()['quotation']['next_number']], 'B starts from its own defaults.');
    }
}
