<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Application\Export\ColumnKind;
use App\Reporting\Application\Export\CsvEncoder;
use App\Reporting\Application\Export\ReportColumn;
use App\Reporting\Application\Export\TabularReport;
use PHPUnit\Framework\TestCase;

final class CsvEncoderTest extends TestCase
{
    private static function csv(TabularReport $report): string
    {
        return implode('', iterator_to_array(CsvEncoder::chunks($report), false));
    }

    public function testExcelOpensItRight(): void
    {
        $csv = self::csv(new TabularReport('R', 'r', [new ReportColumn('Cliente'), new ReportColumn('Total', ColumnKind::Money)], [['Ñandú; S.A.S.', '1190000.00']], static fn () => ['Total', '1190000.00']));

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv, 'UTF-8 with a BOM.');
        self::assertSame("Cliente;Total\r\n\"Ñandú; S.A.S.\";1190000.00\r\nTotal;1190000.00\r\n", substr($csv, 3), '; between values, a decimal point, CRLF, the totals row last.');
    }

    public function testQuotesAreDoubled(): void
    {
        self::assertSame("\"dijo \"\"hola\"\"\"\r\n", CsvEncoder::line(['dijo "hola"']));
    }

    public function testATextThatLooksLikeAFormulaIsDefused(): void
    {
        self::assertSame("\"'=HYPERLINK(\"\"x\"\")\";'+1;'@a;-5.00;-12.50;'-cmd\r\n", CsvEncoder::line(['=HYPERLINK("x")', '+1', '@a', '-5.00', '-12.50', '-cmd']), 'Amounts keep their minus sign; text does not start a formula.');
    }

    public function testRowsStream(): void
    {
        $rows = (static function (): \Generator {
            yield ['a'];
            yield ['b'];
        })();

        self::assertSame("H\r\na\r\nb\r\n", substr(self::csv(new TabularReport('R', 'r', [new ReportColumn('H')], $rows)), 3));
    }
}
