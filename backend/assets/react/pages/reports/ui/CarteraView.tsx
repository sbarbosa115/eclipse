import {Link, useSearchParams} from 'react-router-dom';
import {
  actionClass,
  Actions,
  ErrorState,
  FilterBar,
  Loading,
  Pager,
  TabIntro,
} from '@/shared/ui';
import {DateInput} from '@/shared/ui';
import {useTranslation} from '@/shared/i18n';
import {formatMoney, todayInColombia} from '@/shared/lib';
import {exportUrl, ReportTable} from '@/widgets/report-table';
import {
  fetchCartera,
  type CarteraRow,
  type CarteraSide,
} from '../api/reportsApi';
import {useLoaded} from '../lib/useLoaded';

const PER_PAGE = 25;
const SLUG: Record<CarteraSide, string> = {
  clients: 'clientes',
  suppliers: 'proveedores',
};

/**
 * Cartera de clientes / de proveedores (§4.13): what is owed by tercero, split by how late it is as of a date. The
 * date, the search and the page live in the address, so a reload or a link keeps them.
 */
export function CarteraView({side}: {side: CarteraSide}) {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const asOf = params.get('as_of') || todayInColombia();
  const q = params.get('q') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const change = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(name, value);
    else next.delete(name);
    if (name !== 'page') next.delete('page');
    setParams(next, {replace: true});
  };

  const {data, failed, busy, retry} = useLoaded(
    `${side}|${asOf}|${q}|${page}`,
    () => fetchCartera(side, {as_of: asOf, q, page, per_page: PER_PAGE}),
  );
  const name = t(`reports.cartera.${side}.title`);
  const totals = data?.totals;
  const bucket = (
    header: string,
    pick: (r: CarteraRow) => string,
    total?: string,
  ) => ({
    header,
    numeric: true,
    cell: (r: CarteraRow) => formatMoney(pick(r)),
    total: total === undefined ? undefined : formatMoney(total),
  });
  const documentsLink = (row: CarteraRow) =>
    `/reportes/${SLUG[side]}/${row.tercero_id}?as_of=${asOf}`;

  return (
    <>
      <TabIntro>{t(`reports.cartera.${side}.intro`)}</TabIntro>
      <FilterBar
        search={q}
        onSearch={(value) => change('q', value.trim())}
        searchPlaceholder={t(`reports.cartera.${side}.searchPlaceholder`)}
      >
        <label className="filter-select">
          <span className="filter-select-label">
            {t('reports.cartera.asOf')}
          </span>
          <DateInput
            value={asOf}
            onChange={(iso) => iso && change('as_of', iso)}
          />
        </label>
      </FilterBar>
      {failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : (
        <>
          <ReportTable<CarteraRow>
            busy={busy}
            rows={data.items}
            rowKey={(r) => r.tercero_id}
            totalLabel={t('reports.cartera.total')}
            empty={
              q
                ? t('reports.cartera.noMatches')
                : t(`reports.cartera.${side}.empty`)
            }
            exports={{
              name,
              csv: exportUrl(`/reports/cartera/${side}/export`, 'csv', {
                as_of: asOf,
                q,
              }),
              pdf: exportUrl(`/reports/cartera/${side}/export`, 'pdf', {
                as_of: asOf,
                q,
              }),
            }}
            columns={[
              {
                header: t(`reports.cartera.${side}.tercero`),
                cell: (r) => (
                  <>
                    <strong>{r.name}</strong>
                    <br />
                    <small className="muted">{r.identification}</small>
                  </>
                ),
              },
              bucket(
                t('reports.cartera.buckets.current'),
                (r) => r.current,
                totals?.current,
              ),
              bucket(
                t('reports.cartera.buckets.days1_to30'),
                (r) => r.days1_to30,
                totals?.days1_to30,
              ),
              bucket(
                t('reports.cartera.buckets.days31_to60'),
                (r) => r.days31_to60,
                totals?.days31_to60,
              ),
              bucket(
                t('reports.cartera.buckets.days61_to90'),
                (r) => r.days61_to90,
                totals?.days61_to90,
              ),
              bucket(
                t('reports.cartera.buckets.over90'),
                (r) => r.over90,
                totals?.over90,
              ),
              bucket(
                t('reports.cartera.buckets.total'),
                (r) => r.total,
                totals?.total,
              ),
            ]}
            rowActions={(row) => (
              <Actions>
                <Link className={actionClass('open')} to={documentsLink(row)}>
                  {t('reports.cartera.viewDocuments', {name: row.name})}
                </Link>
              </Actions>
            )}
          />
          <Pager data={data} onPage={(next) => change('page', String(next))} />
        </>
      )}
    </>
  );
}
