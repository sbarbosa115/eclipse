import {Link, useParams, useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney, todayInColombia} from '@/shared/lib';
import {ErrorState, Loading, PageHeader} from '@/shared/ui';
import {ReportTable} from '@/widgets/report-table';
import './reports.css';
import {
  fetchCarteraDocuments,
  type CarteraDocument,
  type CarteraSide,
} from '../api/reportsApi';
import {useLoaded} from '../lib/useLoaded';

const INVOICE_PATH: Record<CarteraSide, string> = {
  clients: '/facturas-venta',
  suppliers: '/facturas-compra',
};

/** How late a document is, in words and a colour (never the colour alone). */
const BUCKET_TONE: Record<string, string> = {
  current: 'ok',
  days1_to30: 'warn',
  days31_to60: 'warn',
  days61_to90: 'bad',
  over90: 'bad',
};

/** The drill-down of one tercero's cartera: each open receivable / payable, its invoice and how late it is. */
export function CarteraDocumentsView({side}: {side: CarteraSide}) {
  const {t} = useTranslation();
  const {terceroId = ''} = useParams();
  const [params] = useSearchParams();
  const asOf = params.get('as_of') || todayInColombia();
  const {data, failed, retry} = useLoaded(`${side}|${terceroId}|${asOf}`, () =>
    fetchCarteraDocuments(side, terceroId, asOf),
  );
  const back =
    side === 'clients' ? '/reportes/clientes' : '/reportes/proveedores';

  return (
    <>
      <PageHeader
        title={data?.tercero_name ?? t(`reports.cartera.${side}.title`)}
        subtitle={t('reports.documents.subtitle', {date: formatDate(asOf)})}
        actions={
          <Link className="btn btn-ghost" to={`${back}?as_of=${asOf}`}>
            {t('reports.documents.back')}
          </Link>
        }
      />
      {failed ? (
        <ErrorState message={t('reports.documents.notFound')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : (
        <ReportTable<CarteraDocument>
          rows={data.items}
          rowKey={(d) => d.id}
          totalLabel={t('reports.cartera.total')}
          columns={[
            {
              header: t('reports.documents.invoice'),
              cell: (d) => (
                <Link to={`${INVOICE_PATH[side]}/${d.invoice_id}`}>
                  {d.invoice_number}
                </Link>
              ),
            },
            {
              header: t('reports.documents.issued'),
              cell: (d) => formatDate(d.issue_date),
            },
            {
              header: t('reports.documents.due'),
              cell: (d) => formatDate(d.due_date),
            },
            {
              header: t('reports.documents.status'),
              cell: (d) => (
                <span
                  className={`report-late report-late-${BUCKET_TONE[d.bucket] ?? 'ok'}`}
                >
                  {d.days_overdue > 0
                    ? t('reports.documents.daysLate', {days: d.days_overdue})
                    : t('reports.cartera.buckets.current')}
                </span>
              ),
            },
            {
              header: t('reports.documents.amount'),
              numeric: true,
              cell: (d) => formatMoney(d.amount),
            },
            {
              header: t('reports.documents.balance'),
              numeric: true,
              cell: (d) => formatMoney(d.balance),
              total: formatMoney(data.total),
            },
          ]}
        />
      )}
    </>
  );
}
