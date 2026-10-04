import {useCallback, useEffect, useState} from 'react';
import {Link, useNavigate, useSearchParams} from 'react-router-dom';
import {
  canVoid,
  duplicatePurchaseInvoice,
  listPurchaseInvoices,
  purchaseErrorMessage,
  purchaseInvoicePdfUrl,
  STATUSES,
  VoidPurchaseInvoiceModal,
  type PurchaseInvoicePage,
  type PurchaseInvoiceSummary,
} from '@/entities/purchase-invoice';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {
  actionClass,
  ActionButton,
  Actions,
  Alert,
  Button,
  DataTable,
  DateInput,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  Pager,
  Row,
  RowLegend,
} from '@/shared/ui';
import './purchaseInvoiceList.css';

const PER_PAGE = 25;
/** Where a page lives: the router's facturas-compra/* (app/App.tsx). */
const BASE = '/facturas-compra';

type Result =
  | {key: string; status: 'ok'; data: PurchaseInvoicePage}
  | {key: string; status: 'failed'};

/**
 * The facturas de compra (§4.15): search (internal number, supplier's number or supplier), status, a date range and
 * pages, with each row's actions: open, duplicate, PDF and void (while nothing is paid). The filters live in the
 * address, so reloading keeps them.
 */
export function PurchaseInvoiceList({canWrite}: {canWrite: boolean}) {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const status = params.get('status') ?? '';
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);

  const [result, setResult] = useState<Result | null>(null);
  const [reloads, setReloads] = useState(0);
  const [notice, setNotice] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [voiding, setVoiding] = useState<PurchaseInvoiceSummary | null>(null);
  const [busyRow, setBusyRow] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listPurchaseInvoices({q, status, from, to, page, per_page: PER_PAGE})
      .then((data) => !cancelled && setResult({key, status: 'ok', data}))
      .catch(() => !cancelled && setResult({key, status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [key, q, status, from, to, page]);

  const reload = useCallback(() => setReloads((n) => n + 1), []);

  const setFilter = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(name, value);
    else next.delete(name);
    if (name !== 'page') next.delete('page');
    setParams(next, {replace: true});
  };
  const showAll = () => setParams(new URLSearchParams(), {replace: true});

  const duplicate = async (invoice: PurchaseInvoiceSummary) => {
    setFailure(null);
    setBusyRow(invoice.id);
    try {
      const copy = await duplicatePurchaseInvoice(invoice.id);
      navigate(`${BASE}/${copy.id}`, {
        state: {notice: t('purchaseInvoice.notices.duplicated')},
      });
    } catch (error) {
      setFailure(purchaseErrorMessage(error, t));
      setBusyRow(null);
    }
  };

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  // Keep showing the last answer, dimmed, while the next one is on its way.
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;

  return (
    <>
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => setFilter('q', value.trim())}
        searchPlaceholder={t('purchaseInvoice.list.searchPlaceholder')}
        filters={[
          {
            name: 'status',
            label: t('purchaseInvoice.list.statusFilter'),
            value: status,
            onChange: (value) => setFilter('status', value),
            options: [
              {value: '', label: t('purchaseInvoice.list.allStatuses')},
              ...STATUSES.map((s) => ({
                value: s,
                label: t(`purchaseInvoice.status.${s}`),
              })),
            ],
          },
        ]}
      >
        <label className="filter-select purchase-list-date">
          <span className="filter-select-label">
            {t('purchaseInvoice.list.from')}
          </span>
          <DateInput value={from} onChange={(iso) => setFilter('from', iso)} />
        </label>
        <label className="filter-select purchase-list-date">
          <span className="filter-select-label">
            {t('purchaseInvoice.list.to')}
          </span>
          <DateInput value={to} onChange={(iso) => setFilter('to', iso)} />
        </label>
      </FilterBar>
      {result === null && <Loading />}
      {result?.status === 'failed' && !busy && (
        <ErrorState message={t('common.loadFailed')} onRetry={reload} />
      )}
      {shown !== null &&
        (shown.items.length === 0 ? (
          filtered ? (
            <EmptyState
              action={
                <Button variant="ghost" onClick={showAll}>
                  {t('common.showAll')}
                </Button>
              }
            >
              {t('purchaseInvoice.list.filteredEmpty')}
            </EmptyState>
          ) : (
            <EmptyState
              action={
                canWrite && (
                  <Link to={`${BASE}/nueva`} className="btn btn-primary">
                    {t('purchaseInvoice.list.emptyAction')}
                  </Link>
                )
              }
            >
              {t('purchaseInvoice.list.empty')}
            </EmptyState>
          )
        ) : (
          <>
            <RowLegend
              statuses={(
                [
                  'draft',
                  'emitted',
                  'partially_paid',
                  'paid',
                  'voided',
                ] as const
              ).map((s) => ({
                value: s,
                label: t(`purchaseInvoice.status.${s}`),
              }))}
            />
            <DataTable
              columns={[
                t('purchaseInvoice.list.columns.number'),
                t('purchaseInvoice.list.columns.supplier'),
                t('purchaseInvoice.list.columns.supplierNumber'),
                t('purchaseInvoice.list.columns.date'),
                t('purchaseInvoice.list.columns.dueDate'),
                t('purchaseInvoice.list.columns.total'),
                t('purchaseInvoice.list.columns.balance'),
              ]}
              rows={shown.items}
              busy={busy}
              renderRow={(invoice) => (
                <Row
                  key={invoice.id}
                  status={invoice.status}
                  label={t(`purchaseInvoice.status.${invoice.status}`)}
                >
                  <td>
                    <Link to={`${BASE}/${invoice.id}`}>
                      {invoice.number ?? t('purchaseInvoice.list.draftNumber')}
                    </Link>
                  </td>
                  <td>{invoice.tercero_name}</td>
                  <td>{invoice.supplier_invoice_number}</td>
                  <td>{formatDate(invoice.issue_date)}</td>
                  <td>{formatDate(invoice.due_date)}</td>
                  <td className="purchase-list-num">
                    {formatMoney(invoice.net_total)}
                  </td>
                  <td className="purchase-list-num">
                    {formatMoney(invoice.balance)}
                  </td>
                  <Actions>
                    <Link
                      to={`${BASE}/${invoice.id}`}
                      className={actionClass(
                        canWrite && invoice.status === 'draft'
                          ? 'edit'
                          : 'open',
                      )}
                    >
                      {t(
                        canWrite && invoice.status === 'draft'
                          ? 'purchaseInvoice.actions.edit'
                          : 'purchaseInvoice.actions.open',
                      )}
                    </Link>
                    <a
                      href={purchaseInvoicePdfUrl(invoice.id)}
                      className={actionClass('open')}
                      download
                    >
                      {t('purchaseInvoice.actions.pdf')}
                    </a>
                    {canWrite && (
                      <ActionButton
                        action="setup"
                        busy={busyRow === invoice.id}
                        onClick={() => duplicate(invoice)}
                      >
                        {t('purchaseInvoice.actions.duplicate')}
                      </ActionButton>
                    )}
                    {canWrite && canVoid(invoice) && (
                      <ActionButton
                        action="danger"
                        onClick={() => setVoiding(invoice)}
                      >
                        {t('purchaseInvoice.actions.void')}
                      </ActionButton>
                    )}
                  </Actions>
                </Row>
              )}
            />
            <Pager data={shown} onPage={(p) => setFilter('page', String(p))} />
          </>
        ))}
      {voiding && (
        <VoidPurchaseInvoiceModal
          invoice={voiding}
          onClose={() => setVoiding(null)}
          onVoided={() => {
            setNotice(
              t('purchaseInvoice.notices.voided', {
                number: voiding.number ?? '',
              }),
            );
            setVoiding(null);
            reload();
          }}
        />
      )}
    </>
  );
}
