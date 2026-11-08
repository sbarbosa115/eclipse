<?php

namespace App\Tests\Functional\Ledger;

use App\Tests\Support\ApiTestCase;

/**
 * GET /accounts/search, what every account picker asks (observations PRD §4.1): every postable account the company
 * created is findable by its parent's code or by words of its name, the answer says how many matched in all, and a
 * subcuenta with active auxiliares is no longer offered.
 */
final class AccountSearchApiTest extends ApiTestCase
{
    use LedgerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startCompany();
    }

    /** @return array<mixed> */
    private function search(string $query): array
    {
        $answer = $this->getJson('/api/v1/accounts/search?'.$query);
        self::assertResponseIsSuccessful();

        return $answer;
    }

    private function addUnder(string $parent, string $code, string $name): void
    {
        $this->sendJson('POST', '/api/v1/accounts', ['parent_code' => $parent, 'code' => $code, 'name' => $name]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testTheCompanysAuxiliaresAreFoundByTheirParentsCode(): void
    {
        $this->addUnder('413595', '41359501', 'Ventas gravados');
        $this->addUnder('413595', '41359502', 'Productos gravados celulares');

        $answer = $this->search('q=4135');

        $codes = array_column($answer['items'], 'code');
        self::assertContains('41359501', $codes, 'The client\'s case: 4135 has 38 PUC subcuentas, and the new auxiliares come after them.');
        self::assertContains('41359502', $codes);
        self::assertNotContains('413595', $codes, 'Its parent has auxiliares now: it takes no movements, so it is not offered.');
        self::assertSame(39, $answer['total'], '37 other subcuentas of 4135 plus the two auxiliares.');
        $sorted = $codes;
        sort($sorted, \SORT_STRING);
        self::assertSame($sorted, $codes, 'Results keep code order.');
    }

    public function testEveryWordOfTheQueryMatchesTheName(): void
    {
        $this->addUnder('413595', '41359501', 'Ventas gravados');
        $this->addUnder('413595', '41359502', 'Productos gravados celulares');

        self::assertSame(['41359501', '41359502'], array_column($this->search('q=gravados')['items'], 'code'));
        self::assertSame(['41359502'], array_column($this->search('q='.rawurlencode('gravados celulares'))['items'], 'code'), 'Words narrow the search: all of them must be in the name.');
        self::assertSame(['41359502'], array_column($this->search('q='.rawurlencode('celulares   productos'))['items'], 'code'), 'In any order, however spaced.');
        self::assertSame(['240805'], array_column($this->search('q='.rawurlencode('iva generado'))['items'], 'code'));
    }

    public function testAPageOfFiftyByDefaultAndTheTotalOfEveryMatch(): void
    {
        $answer = $this->search('q=4');

        self::assertCount(50, $answer['items'], 'Fifty by default.');
        self::assertGreaterThan(50, $answer['total'], 'The total counts every match, so the picker says "Mostrando 50 de N".');

        $more = $this->search('q=4&limit=120');
        self::assertCount(120, $more['items']);
        self::assertSame($answer['total'], $more['total']);
        self::assertCount(200, $this->search('limit=1000')['items'], 'At most 200.');
        self::assertCount(1, $this->search('q=4&limit=0')['items'], 'At least one.');
    }

    public function testPurchasesNarrowToAccountsUsableOnPurchaseLines(): void
    {
        $answer = $this->search('q=5135&purchases=1');

        self::assertNotSame([], $answer['items']);
        self::assertSame([true], array_values(array_unique(array_column($answer['items'], 'usable_on_purchases'))));
        self::assertSame(\count($answer['items']), $answer['total']);
    }

    public function testAnotherCompanysAccountsAreNeverFound(): void
    {
        $this->addUnder('413595', '41359501', 'Ventas gravados de Acme');
        $this->signOut();
        $this->startCompany('beto@b.co', '800197268', 'B S.A.S.');

        $answer = $this->search('q=gravados');

        self::assertSame([], $answer['items']);
        self::assertSame(0, $answer['total']);
        self::assertContains('413595', array_column($this->search('q=4135')['items'], 'code'), 'Its own 413595 has no auxiliares.');
    }

    public function testPercentMatchesItselfOnly(): void
    {
        self::assertSame(0, $this->search('q=%25')['total']);
    }
}
