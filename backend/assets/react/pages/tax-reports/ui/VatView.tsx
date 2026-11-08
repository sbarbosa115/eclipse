import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {Alert, ErrorState, FilterBar, Loading, TabIntro} from '@/shared/ui';
import {ReportTable} from '@/widgets/report-table';
import {
  fetchVat,
  VAT_PATH,
  type VatRateRow,
  type VatReport,
} from '../api/taxReportsApi';
import {formatRate} from '../lib/period';
import {useLoaded} from '../lib/useLoaded';
import {usePeriod} from '../model/usePeriod';
import {BooksCheck} from './BooksCheck';
import {TaxExports} from './TaxExports';
import {VoidedNote} from './VoidedNote';

interface SummaryRow {
  key: string;
  concept: string;
  value: string;
}

/**
 * IVA (observations PRD §4.7 c): generado by rate from the sales, descontable by rate from the purchases, each with its
 * *Según libros*, and the saldo of the return. A bimester or a cuatrimestre is a range of months.
 */
export function VatView() {
  const {t} = useTranslation();
  const {from, to, problem, filters} = usePeriod();
  const {data, failed, busy, retry} = useLoaded(
    problem ? 'vat|invalid' : `vat|${from}|${to}`,
    () => (problem ? Promise.resolve(null) : fetchVat({from, to})),
  );

  return (
    <>
      <TabIntro>{t('taxReports.vat.intro')}</TabIntro>
      <FilterBar filters={filters} />
      {problem ? (
        <Alert kind="warning">{problem}</Alert>
      ) : (
        <TaxExports
          name={t('taxReports.tabs.vat')}
          path={VAT_PATH}
          params={{from, to}}
        />
      )}
      {problem ? null : failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : (
        <>
          <VoidedNote count={data.voided_count} />
          <RatesTable
            title={t('taxReports.vat.generated')}
            empty={t('taxReports.vat.emptyGenerated')}
            rows={data.generated}
            total={data.generated_total}
            busy={busy}
          />
          <BooksCheck
            accounts={t('taxReports.vat.generatedAccounts')}
            ledger={data.ledger_generated}
            difference={data.generated_difference}
          />
          <RatesTable
            title={t('taxReports.vat.deductible')}
            empty={t('taxReports.vat.emptyDeductible')}
            rows={data.deductible}
            total={data.deductible_total}
            busy={busy}
          />
          <BooksCheck
            accounts={t('taxReports.vat.deductibleAccounts')}
            ledger={data.ledger_deductible}
            difference={data.deductible_difference}
          />
          <Summary report={data} busy={busy} />
        </>
      )}
    </>
  );
}

function RatesTable({
  title,
  empty,
  rows,
  total,
  busy,
}: {
  title: string;
  empty: string;
  rows: VatRateRow[];
  total: string;
  busy: boolean;
}) {
  const {t} = useTranslation();
  const base = rows.length > 0 ? undefined : '';
  return (
    <section className="tax-section" aria-label={title}>
      <h3 className="tax-section-title">{title}</h3>
      <ReportTable<VatRateRow>
        busy={busy}
        rows={rows}
        rowKey={(r) => r.rate}
        empty={empty}
        totalLabel={t('taxReports.vat.total')}
        columns={[
          {
            header: t('taxReports.vat.rate'),
            cell: (r) => formatRate(t, 'percentage', r.rate),
          },
          {
            header: t('taxReports.vat.base'),
            numeric: true,
            cell: (r) => formatMoney(r.base),
            total: base,
          },
          {
            header: t('taxReports.vat.tax'),
            numeric: true,
            cell: (r) => formatMoney(r.tax),
            total: formatMoney(total),
          },
        ]}
      />
    </section>
  );
}

/** The saldo of the return, then what the period also holds but is declared elsewhere. */
function Summary({report, busy}: {report: VatReport; busy: boolean}) {
  const {t} = useTranslation();
  const inFavor = report.net.startsWith('-');
  const saldo: SummaryRow[] = [
    {
      key: 'generated',
      concept: t('taxReports.vat.generatedTotal'),
      value: formatMoney(report.generated_total),
    },
    {
      key: 'deductible',
      concept: t('taxReports.vat.deductibleTotal'),
      value: formatMoney(report.deductible_total),
    },
    {
      key: 'reteivaSuffered',
      concept: t('taxReports.vat.reteivaSuffered'),
      value: formatMoney(report.reteiva_suffered),
    },
  ];
  const others: SummaryRow[] = [
    {
      key: 'reteivaPracticed',
      concept: t('taxReports.vat.reteivaPracticed'),
      value: formatMoney(report.reteiva_practiced),
    },
    {
      key: 'impoconsumo',
      concept: t('taxReports.vat.impoconsumo'),
      value: formatMoney(report.impoconsumo),
    },
  ];
  const columns = (total?: string) => [
    {header: t('taxReports.vat.concept'), cell: (r: SummaryRow) => r.concept},
    {
      header: t('taxReports.vat.value'),
      numeric: true,
      cell: (r: SummaryRow) => r.value,
      total,
    },
  ];

  return (
    <>
      <section className="tax-section" aria-label={t('taxReports.vat.summary')}>
        <h3 className="tax-section-title">{t('taxReports.vat.summary')}</h3>
        <ReportTable<SummaryRow>
          busy={busy}
          rows={saldo}
          rowKey={(r) => r.key}
          totalLabel={
            inFavor ? t('taxReports.vat.inFavor') : t('taxReports.vat.toPay')
          }
          columns={columns(
            formatMoney(inFavor ? report.net.slice(1) : report.net),
          )}
        />
      </section>
      <section className="tax-section" aria-label={t('taxReports.vat.others')}>
        <h3 className="tax-section-title">{t('taxReports.vat.others')}</h3>
        <ReportTable<SummaryRow>
          busy={busy}
          rows={others}
          rowKey={(r) => r.key}
          columns={columns()}
        />
      </section>
    </>
  );
}
