<?php

namespace App\Reporting\Application\Export;

use App\Reporting\Application\Cartera\CarteraDocument;
use App\Reporting\Application\Cartera\CarteraQueries;
use App\Reporting\Application\Cartera\CarteraRow;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/** Cartera de clientes / de proveedores as exportable tables: by tercero, or document by document. */
final class CarteraTables
{
    public function __construct(private readonly CarteraQueries $cartera)
    {
    }

    /** @throws ExportTooLarge */
    public function byTercero(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search, int $limit): TabularReport
    {
        $totals = $this->cartera->totals($companyId, $side, $asOf, $search);
        if ($totals->terceros > $limit) {
            throw new ExportTooLarge($totals->terceros, $limit);
        }
        $rows = array_map(static fn (CarteraRow $r) => [$r->name, $r->identification, $r->current, $r->days1To30, $r->days31To60, $r->days61To90, $r->over90, $r->total], $this->cartera->summary($companyId, $side, $asOf, $search));

        return new TabularReport(
            self::title($side),
            self::file($side, $asOf, 'resumen'),
            [
                new ReportColumn(self::terceroLabel($side)),
                new ReportColumn('Identificación'),
                new ReportColumn('Al día', ColumnKind::Money),
                new ReportColumn('1-30 días', ColumnKind::Money),
                new ReportColumn('31-60 días', ColumnKind::Money),
                new ReportColumn('61-90 días', ColumnKind::Money),
                new ReportColumn('Más de 90 días', ColumnKind::Money),
                new ReportColumn('Total', ColumnKind::Money),
            ],
            $rows,
            static fn () => ['Total', '', $totals->current, $totals->days1To30, $totals->days31To60, $totals->days61To90, $totals->over90, $totals->total],
            self::subtitle($asOf, $search),
        );
    }

    /** @throws ExportTooLarge */
    public function documents(Uuid $companyId, CarteraSide $side, \DateTimeImmutable $asOf, ?string $search, int $limit): TabularReport
    {
        $totals = $this->cartera->totals($companyId, $side, $asOf, $search);
        if ($totals->documents > $limit) {
            throw new ExportTooLarge($totals->documents, $limit);
        }
        $documents = $this->cartera->documents($companyId, $side, $asOf, null, $search);
        $rows = array_map(static fn (CarteraDocument $d) => [$d->terceroName, $d->invoiceNumber, $d->issueDate, $d->dueDate, (string) max(0, $d->daysOverdue), $d->bucket->value, $d->amount, $d->balance], $documents);

        return new TabularReport(
            self::title($side).' - detalle',
            self::file($side, $asOf, 'detalle'),
            [
                new ReportColumn(self::terceroLabel($side)),
                new ReportColumn('Factura'),
                new ReportColumn('Fecha', ColumnKind::Date),
                new ReportColumn('Vencimiento', ColumnKind::Date),
                new ReportColumn('Días vencida', ColumnKind::Number),
                new ReportColumn('Rango'),
                new ReportColumn('Valor', ColumnKind::Money),
                new ReportColumn('Saldo', ColumnKind::Money),
            ],
            $rows,
            static fn () => ['Total', '', '', '', '', '', '', Money::sum(...array_map(static fn (CarteraDocument $d) => Money::of($d->balance), $documents))->toString()],
            self::subtitle($asOf, $search),
        );
    }

    private static function title(CarteraSide $side): string
    {
        return CarteraSide::Clients === $side ? 'Cartera de clientes' : 'Cartera de proveedores';
    }

    private static function terceroLabel(CarteraSide $side): string
    {
        return CarteraSide::Clients === $side ? 'Cliente' : 'Proveedor';
    }

    private static function file(CarteraSide $side, \DateTimeImmutable $asOf, string $kind): string
    {
        return \sprintf('cartera-%s-%s-%s', CarteraSide::Clients === $side ? 'clientes' : 'proveedores', $kind, $asOf->format('Y-m-d'));
    }

    /** @return list<string> */
    private static function subtitle(\DateTimeImmutable $asOf, ?string $search): array
    {
        return array_values(array_filter([
            'Al '.$asOf->format('d/m/Y'),
            null === $search || '' === trim($search) ? null : 'Búsqueda: '.trim($search),
        ]));
    }
}
