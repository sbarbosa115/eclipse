<?php

namespace App\Tests\Functional\Sales;

use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * §5 invariant 3, for a client: balance in 1305 = Σ emitted sales invoices on crédito − Σ receipts − Σ voided amounts
 * (voided invoices and voided receipts both undo what they did), checked after every step of a story that does all of
 * it.
 */
final class ClientBalanceInvariantTest extends ApiTestCase
{
    use CashReceiptFixtures;

    public function testTheClientsBalanceIn1305FollowsItsDocuments(): void
    {
        $this->startCompany();
        $client = $this->client();
        $expect = function (string $why) use ($client): void {
            self::assertSame($this->fromDocuments($client)->toString(), $this->clientesBalance($client), $why);
        };

        $a = $this->onCredit($client);
        $b = $this->emitted([$this->cash('190000.00'), $this->credit('1000000.00')], $client);
        $c = $this->onCredit($client);
        $this->emitted([$this->cash('1190000.00')], $client);
        $expect('Invoices on crédito (an all-cash one adds nothing).');
        self::assertSame('3380000.00', $this->clientesBalance($client));

        $paysA = $this->receive($client, '1190000.00', [[$a['receivables'][0]['id'], '1190000.00']]);
        $this->receive($client, '400000.00', [[$b['receivables'][0]['id'], '300000.00'], [$c['receivables'][0]['id'], '100000.00']]);
        $expect('After receipts.');

        $this->sendJson('POST', '/api/v1/cash-receipts/'.$paysA['id'].'/void', ['reason' => 'Cheque devuelto']);
        self::assertResponseIsSuccessful();
        $expect('After a voided receipt.');

        $this->sendJson('POST', '/api/v1/sales-invoices/'.$a['id'].'/void', ['reason' => 'Factura errada']);
        self::assertResponseIsSuccessful();
        $expect('After a voided invoice.');
        self::assertSame('1790000.00', $this->clientesBalance($client), 'b 1.000.000 + c 1.190.000 on crédito − 400.000 collected.');
    }

    /** Σ crédito of emitted invoices − Σ emitted receipts, from the documents' own tables (voided ones count nothing). */
    private function fromDocuments(string $client): Money
    {
        $db = $this->em()->getConnection();
        $id = Uuid::fromString($client)->toBinary();
        $invoiced = $db->fetchOne(
            "SELECT COALESCE(SUM(p.amount), 0) FROM sales_invoice_payment p JOIN sales_invoice i ON i.id = p.invoice_id
             WHERE i.company_id = ? AND i.tercero_id = ? AND i.status NOT IN ('draft', 'voided') AND p.kind = 'credit'",
            [$this->company->toBinary(), $id],
        );
        $received = $db->fetchOne("SELECT COALESCE(SUM(amount), 0) FROM cash_receipt WHERE company_id = ? AND tercero_id = ? AND status = 'emitted'", [$this->company->toBinary(), $id]);

        return Money::of((string) $invoiced)->minus(Money::of((string) $received));
    }
}
