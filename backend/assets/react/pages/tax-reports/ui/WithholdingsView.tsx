import {Link} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {
  actionClass,
  Actions,
  Alert,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  TabIntro,
} from '@/shared/ui';
import {ReportTable} from '@/widgets/report-table';
import {
  fetchWithholdings,
  WITHHOLDINGS_PATH,
  type WithholdingGroup,
  type WithholdingKind,
  type WithholdingKindTotal,
  type WithholdingRow,
  type WithholdingSide,
} from '../api/taxReportsApi';
import {formatRate, monthDays, monthLabel} from '../lib/period';
import {useLoaded} from '../lib/useLoaded';
import {usePeriod} from '../model/usePeriod';
import {BooksCheck} from './BooksCheck';
import {TaxExports} from './TaxExports';
import {VoidedNote} from './VoidedNote';

const KINDS: WithholdingKind[] = ['retefuente', 'reteiva', 'reteica'];
const DOCUMENTS: Record<WithholdingSide, string> = {
  practiced: '/facturas-compra',
  suffered: '/facturas-venta',
};

/**
 * Retenciones practicadas (facturas de compra) or sufridas (facturas de venta), observations PRD §4.7 a–b: one table
 * per month and kind with its total and *Según libros*, then the period's totals per kind. The period and the kind
 * live in the address.
 */
export function WithholdingsView({side}: {side: WithholdingSide}) {
  const {t} = useTranslation();
  const {from, to, problem, filters, params, change} = usePeriod();
  const kindParam = params.get('kind') ?? '';
  const kind = KINDS.find((k) => k === kindParam);

  const {data, failed, busy, retry} = useLoaded(
    problem ? `${side}|invalid` : `${side}|${from}|${to}|${kind ?? ''}`,
    () =>
      problem
        ? Promise.resolve(null)
        : fetchWithholdings(side, {from, to}, kind),
  );
  const accounts = (k: string) =>
    t(`taxReports.withholdings.${side}.accounts.${k}`);
  const kindLabel = (k: string) => t(`taxReports.kinds.${k}`);

  return (
    <>
      <TabIntro>{t(`taxReports.withholdings.${side}.intro`)}</TabIntro>
      <FilterBar
        filters={[
          ...filters,
          {
            name: 'kind',
            label: t('taxReports.kinds.label'),
            value: kind ?? '',
            onChange: (value) => change('kind', value),
            options: [
              {value: '', label: t('taxReports.kinds.all')},
              ...KINDS.map((k) => ({value: k, label: kindLabel(k)})),
            ],
          },
        ]}
      />
      {problem ? (
        <Alert kind="warning">{problem}</Alert>
      ) : (
        <TaxExports
          name={t(`taxReports.tabs.${side}`)}
          path={WITHHOLDINGS_PATH(side)}
          params={{from, to, kind}}
        />
      )}
      {problem ? null : failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : data.groups.length === 0 ? (
        <>
          <VoidedNote count={data.voided_count} />
          <EmptyState>{t(`taxReports.withholdings.${side}.empty`)}</EmptyState>
        </>
      ) : (
        <>
          <VoidedNote count={data.voided_count} />
          {data.groups.map((group) => (
            <GroupTable
              key={`${group.month}|${group.kind}`}
              side={side}
              group={group}
              busy={busy}
              accounts={accounts(group.kind)}
            />
          ))}
          {data.from !== data.to && (
            <section className="tax-section">
              <h3 className="tax-section-title">
                {t('taxReports.withholdings.periodTotals')}
              </h3>
              <ReportTable<WithholdingKindTotal>
                busy={busy}
                rows={data.totals}
                rowKey={(r) => r.kind}
                columns={[
                  {
                    header: t('taxReports.withholdings.kind'),
                    cell: (r) => kindLabel(r.kind),
                  },
                  {
                    header: t('taxReports.withholdings.base'),
                    numeric: true,
                    cell: (r) => formatMoney(r.total_base),
                  },
                  {
                    header: t('taxReports.withholdings.amount'),
                    numeric: true,
                    cell: (r) => formatMoney(r.total_amount),
                  },
                  {
                    header: t('taxReports.withholdings.ledger'),
                    numeric: true,
                    cell: (r) => formatMoney(r.ledger_amount),
                  },
                  {
                    header: t('taxReports.withholdings.difference'),
                    numeric: true,
                    cell: (r) => formatMoney(r.difference),
                  },
                ]}
              />
            </section>
          )}
        </>
      )}
    </>
  );
}

function GroupTable({
  side,
  group,
  busy,
  accounts,
}: {
  side: WithholdingSide;
  group: WithholdingGroup;
  busy: boolean;
  accounts: string;
}) {
  const {t} = useTranslation();
  const days = monthDays(group.month);
  const title = t('taxReports.withholdings.group', {
    kind: t(`taxReports.kinds.${group.kind}`),
    month: monthLabel(t, group.month),
  });

  return (
    <section className="tax-section" aria-label={title}>
      <h3 className="tax-section-title">{title}</h3>
      <ReportTable<WithholdingRow>
        busy={busy}
        rows={group.rows}
        rowKey={(r) => `${r.tercero_id}|${r.tax_id ?? r.tax_name}|${r.rate}`}
        totalLabel={t('taxReports.withholdings.total')}
        empty={t('taxReports.withholdings.groupEmpty')}
        rowActions={(r) => (
          <Actions>
            <Link
              className={actionClass('open')}
              aria-label={t('taxReports.withholdings.openDocumentsOf', {
                name: r.tercero_name,
              })}
              to={`${DOCUMENTS[side]}?${new URLSearchParams({q: r.tercero_name, ...days}).toString()}`}
            >
              {t('taxReports.withholdings.openDocuments')}
            </Link>
          </Actions>
        )}
        columns={[
          {
            header: t(`taxReports.withholdings.${side}.tercero`),
            cell: (r) => (
              <>
                <strong>{r.tercero_name}</strong>
                <br />
                <small className="muted">
                  {t(`taxReports.identification.${r.identification_type}`)}{' '}
                  {r.identification_number}
                  {r.check_digit ? `-${r.check_digit}` : ''}
                </small>
              </>
            ),
          },
          {header: t('taxReports.withholdings.tax'), cell: (r) => r.tax_name},
          {
            header: t('taxReports.withholdings.rate'),
            numeric: true,
            cell: (r) => formatRate(t, r.calculation, r.rate),
          },
          {
            header: t('taxReports.withholdings.base'),
            numeric: true,
            cell: (r) => formatMoney(r.base),
            total: formatMoney(group.total_base),
          },
          {
            header: t('taxReports.withholdings.amount'),
            numeric: true,
            cell: (r) => formatMoney(r.amount),
            total: formatMoney(group.total_amount),
          },
          {
            header: t('taxReports.withholdings.documents'),
            numeric: true,
            cell: (r) => r.documents,
          },
        ]}
      />
      <BooksCheck
        accounts={accounts}
        ledger={group.ledger_amount}
        difference={group.difference}
      />
    </section>
  );
}
