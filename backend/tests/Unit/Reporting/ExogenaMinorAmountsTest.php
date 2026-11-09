<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Application\Exogena\ExogenaMinorAmounts;
use App\Reporting\Application\Exogena\ExogenaRow;
use App\Reporting\Application\Exogena\ExogenaTercero;
use App\Shared\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Cuantías menores (observations PRD §4.7 d): a tercero whose total in the format's first column is under the threshold
 * is not reported by name; its rows are added into one row per concept under the DIAN pseudo-NIT 222222222.
 */
final class ExogenaMinorAmountsTest extends TestCase
{
    private static function tercero(string $id): ExogenaTercero
    {
        return new ExogenaTercero($id, 'nit', '9000000'.$id, '1', 'empresa', null, null, "Tercero $id", 'Calle 1', '11001', 'CO');
    }

    private static function row(string $tercero, string $payment, string $withholding = '0', ?string $concept = '5001', ?string $account = '511025'): ExogenaRow
    {
        return new ExogenaRow(self::tercero($tercero), $account, $concept, ['payment' => Money::of($payment), 'withholding' => Money::of($withholding)]);
    }

    /**
     * @param list<ExogenaRow> $rows
     *
     * @return list<array{?string, ?string, array<string, string>}>
     */
    private static function seen(array $rows): array
    {
        return array_map(static fn (ExogenaRow $r) => [$r->tercero?->id, $r->conceptCode, array_map(static fn (Money $m) => $m->toString(), $r->values)], $rows);
    }

    public function testAThresholdOfZeroReportsEveryone(): void
    {
        $rows = [self::row('1', '10.00'), self::row('2', '0.00')];

        [$reported, $minor] = ExogenaMinorAmounts::split($rows, Money::zero());

        self::assertSame($rows, $reported, 'Nothing is grouped.');
        self::assertSame([], $minor);
    }

    public function testATerceroUnderTheThresholdIsGroupedAndOneAtItStaysByName(): void
    {
        [$reported, $minor] = ExogenaMinorAmounts::split([
            self::row('1', '99999.99', '2500.00'),
            self::row('2', '100000.00', '4000.00'),
            self::row('3', '50000.00', '1000.00'),
        ], Money::of('100000'));

        self::assertSame([['2', '5001', ['payment' => '100000.00', 'withholding' => '4000.00']]], self::seen($reported), 'Exactly the threshold is reported by name.');
        self::assertSame([[null, '5001', ['payment' => '149999.99', 'withholding' => '3500.00']]], self::seen($minor), 'Every column of the grouped rows is added up.');
        self::assertNull($minor[0]->accountCode);
    }

    public function testATerceroIsJudgedByItsTotalNotRowByRow(): void
    {
        [$reported, $minor] = ExogenaMinorAmounts::split([
            self::row('1', '60000.00', account: '511025'),
            self::row('1', '60000.00', '0', '5003', '513595'),
            self::row('2', '30000.00'),
        ], Money::of('100000'));

        self::assertSame(['1', '1'], array_map(static fn (ExogenaRow $r) => $r->tercero?->id, $reported), 'Two rows of 60.000 are a tercero of 120.000: both stay.');
        self::assertSame([[null, '5001', ['payment' => '30000.00', 'withholding' => '0.00']]], self::seen($minor));
    }

    public function testTheMinorAmountsAreOneRowPerConceptInConceptOrder(): void
    {
        [, $minor] = ExogenaMinorAmounts::split([
            self::row('1', '10.00', concept: '5003'),
            self::row('2', '20.00', concept: null),
            self::row('3', '30.00', concept: '5001'),
            self::row('4', '40.00', concept: '5003'),
        ], Money::of('1000'));

        self::assertSame([
            [null, null, ['payment' => '20.00', 'withholding' => '0.00']],
            [null, '5001', ['payment' => '30.00', 'withholding' => '0.00']],
            [null, '5003', ['payment' => '50.00', 'withholding' => '0.00']],
        ], self::seen($minor), 'Rows without a concept stay apart from the ones with one, first.');
    }

    public function testACreditBalanceIsMeasuredBySize(): void
    {
        [$reported, $minor] = ExogenaMinorAmounts::split([
            self::row('1', '-500000.00'),
            self::row('2', '-500.00'),
        ], Money::of('100000'));

        self::assertSame(['1'], array_map(static fn (ExogenaRow $r) => $r->tercero?->id, $reported), 'A balance in favour of the tercero of 500.000 is not a cuantía menor.');
        self::assertSame('-500.00', $minor[0]->values['payment']->toString());
    }
}
