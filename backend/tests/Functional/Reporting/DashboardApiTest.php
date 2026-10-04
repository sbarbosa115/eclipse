<?php

namespace App\Tests\Functional\Reporting;

use App\Tests\Support\ApiTestCase;

/** The home screen's numbers in one request (§4.13): cartera, the month's sales and purchases, cash and banks. */
final class DashboardApiTest extends ApiTestCase
{
    use ReportingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startReporting();
    }

    public function testAnEmptyCompanyShowsZeros(): void
    {
        $body = $this->getJson('/api/v1/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'as_of' => self::today(), 'clients_total' => '0.00', 'clients_overdue' => '0.00', 'suppliers_total' => '0.00', 'suppliers_overdue' => '0.00',
            'sales_month' => '0.00', 'sales_month_count' => 0, 'purchases_month' => '0.00', 'purchases_month_count' => 0, 'cash_and_banks' => '0.00',
        ], $body);
    }

    public function testTheNumbersOfACompanyInMotion(): void
    {
        $client = $this->newClient();
        $this->sale($client, 20, 0, '190000.00');   // issued today: 190.000 on crédito, 1.000.000 in cash
        $this->sale($client, 20, 0);
        $voided = $this->sale($client, 20, 0);
        $this->sendJson('POST', '/api/v1/sales-invoices/'.$voided['id'].'/void', ['reason' => 'Error']);
        $this->sale($client, -50, -100);             // an old one, long overdue
        $this->bought($this->supplierNamed('Zeta Ltda.'), 'Z-1', 15, 0);
        $this->bought($this->supplierNamed('Andes S.A.S.'), 'A-1', -50, -100);

        $body = $this->getJson('/api/v1/dashboard');

        self::assertSame('2570000.00', $body['clients_total'], '190.000 + 1.190.000 + 1.190.000 owed.');
        self::assertSame('1190000.00', $body['clients_overdue']);
        self::assertSame('2300000.00', $body['suppliers_total']);
        self::assertSame('1150000.00', $body['suppliers_overdue']);
        self::assertSame('2000000.00', $body['sales_month'], 'Two invoices of 1.000.000 before IVA this month; the voided and the old one count nothing.');
        self::assertSame(2, $body['sales_month_count']);
        self::assertSame('1000000.00', $body['purchases_month']);
        self::assertSame(1, $body['purchases_month_count']);
        self::assertSame('1000000.00', $body['cash_and_banks'], 'Caja: the cash part of the first invoice, from the books.');
    }

    public function testTheBillingUserDoesNotSeeTheBooksFigures(): void
    {
        $this->sale($this->newClient(), 20, 0, '190000.00');
        $this->signInAs('billing');

        $body = $this->getJson('/api/v1/dashboard');

        self::assertResponseIsSuccessful();
        self::assertNull($body['cash_and_banks']);
        self::assertSame('190000.00', $body['clients_total']);

        $this->signInAs('accountant');
        self::assertSame('1000000.00', $this->getJson('/api/v1/dashboard')['cash_and_banks']);
    }

    public function testItIsPerCompanyAndNeedsASession(): void
    {
        $this->sale($this->newClient(), 20, 0, '190000.00');
        $this->signOut();
        $this->getJson('/api/v1/dashboard');
        self::assertResponseStatusCodeSame(401);

        $this->signUp('otra@empresa.co', '900999888', 'Otra S.A.S.');
        self::assertSame('0.00', $this->getJson('/api/v1/dashboard')['clients_total']);
    }

    public function testItNeverAsksMoreThanAHandfulOfQueries(): void
    {
        $client = $this->newClient();
        foreach ([1, 2, 3, 4] as $i) {
            $this->sale($client, $i * 10, 0, '190000.00');
            $this->bought($this->supplierNamed("Proveedor $i S.A.S."), "P-$i", $i * 10, 0);
        }
        $this->client->enableProfiler();

        $this->client->request('GET', '/api/v1/dashboard');

        self::assertResponseIsSuccessful();
        $profile = $this->client->getProfile();
        self::assertInstanceOf(\Symfony\Component\HttpKernel\Profiler\Profile::class, $profile);
        /** @var \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $db */
        $db = $profile->getCollector('db');
        self::assertLessThanOrEqual(12, $db->getQueryCount(), 'The session, the permission and five figures: not a query per tercero or document.');
    }
}
