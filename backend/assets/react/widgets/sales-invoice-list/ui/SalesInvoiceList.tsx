import {useCallback, useEffect, useState} from 'react';
import {Link, useNavigate, useSearchParams} from 'react-router-dom';
import {
  canSend,
  canVoid,
  duplicateSalesInvoice,
  INVOICE_STATUSES,
  listSalesInvoices,
  salesInvoiceErrorMessage,
  salesInvoicePdfUrl,
  sendSalesInvoice,
  voidSalesInvoice,
  type SalesInvoicePage,
  type SalesInvoiceSummary,
} from '@/entities/sales-invoice';
import {VoidDocumentModal} from '@/features/void-document';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {
  actionClass,
  ActionButton,
  Actions,
  Alert,
  Badge,
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
import './salesInvoiceList.css';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: SalesInvoicePage}
  | {key: string; status: 'failed'};

/**
 * The facturas de venta (§4.15): search by number or client, filter by status and date, a page at a time; each row
 * opens, duplicates, downloads its PDF, is sent by e-mail and voided where that is allowed. The filters live in the
 * address, so a reload or a link keeps them.
 */
export function SalesInvoiceList({
  canWrite,
  newPath = 'nueva',
  notice: initialNotice = null,
}: {
  /** The owner and billing users: duplicate, send, void and create. */
  canWrite: boolean;
  /** Where "Crear la primera factura" goes. */
  newPath?: string;
  notice?: string | null;
}) {
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
  const [notice, setNotice] = useState<string | null>(initialNotice);
  const [failure, setFailure] = useState<string | null>(null);
  const [voiding, setVoiding] = useState<SalesInvoiceSummary | null>(null);
  const [working, setWorking] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listSalesInvoices({q, status, from, to, page, per_page: PER_PAGE})
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

  const act = async (
    invoice: SalesInvoiceSummary,
    work: () => Promise<void>,
  ) => {
    setFailure(null);
    setNotice(null);
    setWorking(invoice.id);
    try {
      await work();
    } catch (error) {
      setFailure(salesInvoiceErrorMessage(error, t));
    } finally {
      setWorking(null);
    }
  };

  const duplicate = (invoice: SalesInvoiceSummary) =>
    act(invoice, async () => {
      const copy = await duplicateSalesInvoice(invoice.id);
      navigate(copy.id, {
        state: {notice: t('salesInvoice.notices.duplicated')},
      });
    });

  const send = (invoice: SalesInvoiceSummary) =>
    act(invoice, async () => {
      await sendSalesInvoice(invoice.id);
      setNotice(t('salesInvoice.notices.sent', {number: invoice.number ?? ''}));
    });

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const statusLabel = (value: string) => t(`salesInvoice.statuses.${value}`);

  return (
    <div className="sales-invoice-list">
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => setFilter('q', value.trim())}
        searchPlaceholder={t('salesInvoice.list.searchPlaceholder')}
        filters={[
          {
            name: 'status',
            label: t('salesInvoice.list.statusFilter'),
            value: status,
            onChange: (value) => setFilter('status', value),
            options: [
              {value: '', label: t('salesInvoice.list.allStatuses')},
              ...INVOICE_STATUSES.map((s) => ({
                value: s,
                label: statusLabel(s),
              })),
            ],
          },
        ]}
      >
        <label className="filter-select sales-invoice-date">
          <span className="filter-select-label">
            {t('salesInvoice.list.from')}
          </span>
          <DateInput value={from} onChange={(iso) => setFilter('from', iso)} />
        </label>
        <label className="filter-select sales-invoice-date">
          <span className="filter-select-label">
            {t('salesInvoice.list.to')}
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
              {t('salesInvoice.list.filteredEmpty')}
            </EmptyState>
          ) : (
            <EmptyState
              action={
                canWrite && (
                  <Link to={newPath} className="btn btn-primary">
                    {t('salesInvoice.list.emptyAction')}
                  </Link>
                )
              }
            >
              {t('salesInvoice.list.empty')}
            </EmptyState>
          )
        ) : (
          <>
            <RowLegend
              statuses={INVOICE_STATUSES.map((s) => ({
                value: s,
                label: statusLabel(s),
              }))}
            />
            <DataTable
              columns={[
                t('salesInvoice.list.columns.number'),
                t('salesInvoice.list.columns.client'),
                t('salesInvoice.list.columns.date'),
                t('salesInvoice.list.columns.due'),
                t('salesInvoice.list.columns.total'),
                t('salesInvoice.list.columns.balance'),
              ]}
              rows={shown.items}
              busy={busy}
              renderRow={(invoice) => (
                <Row
                  key={invoice.id}
                  status={invoice.status}
                  label={statusLabel(invoice.status)}
                >
                  <td>
                    <Link to={invoice.id}>
                      {invoice.number ?? t('salesInvoice.list.draftNumber')}
                    </Link>{' '}
                    <Badge value={invoice.status}>
                      {statusLabel(invoice.status)}
                    </Badge>
                  </td>
                  <td>{invoice.tercero_name}</td>
                  <td>{formatDate(invoice.issue_date)}</td>
                  <td>{formatDate(invoice.due_date)}</td>
                  <td className="num">{formatMoney(invoice.net_total)}</td>
                  <td className="num">
                    {invoice.status === 'draft' || invoice.status === 'voided'
                      ? '—'
                      : formatMoney(invoice.balance)}
                  </td>
                  <Actions>
                    <Link
                      to={invoice.id}
                      className={actionClass(
                        canWrite && invoice.status === 'draft'
                          ? 'edit'
                          : 'open',
                      )}
                    >
                      {t(
                        canWrite && invoice.status === 'draft'
                          ? 'salesInvoice.actions.edit'
                          : 'salesInvoice.actions.open',
                      )}
                    </Link>
                    <a
                      href={salesInvoicePdfUrl(invoice.id)}
                      target="_blank"
                      rel="noreferrer"
                      className={actionClass('open')}
                    >
                      {t('salesInvoice.actions.pdf')}
                    </a>
                    {canWrite && (
                      <>
                        <ActionButton
                          action="setup"
                          busy={working === invoice.id}
                          onClick={() => duplicate(invoice)}
                        >
                          {t('salesInvoice.actions.duplicate')}
                        </ActionButton>
                        {canSend(invoice) && (
                          <ActionButton
                            action="confirm"
                            disabled={working === invoice.id}
                            onClick={() => send(invoice)}
                          >
                            {t('salesInvoice.actions.send')}
                          </ActionButton>
                        )}
                        {canVoid(invoice) && (
                          <ActionButton
                            action="danger"
                            disabled={working === invoice.id}
                            onClick={() => setVoiding(invoice)}
                          >
                            {t('salesInvoice.actions.void')}
                          </ActionButton>
                        )}
                      </>
                    )}
                  </Actions>
                </Row>
              )}
            />
            <Pager data={shown} onPage={(p) => setFilter('page', String(p))} />
          </>
        ))}
      {voiding && (
        <VoidDocumentModal
          title={t('salesInvoice.void.title', {number: voiding.number ?? ''})}
          body={t('salesInvoice.void.body')}
          reasonLabel={t('salesInvoice.void.reason')}
          reasonRequired={t('salesInvoice.void.reasonRequired')}
          confirmLabel={t('salesInvoice.void.confirm')}
          onClose={() => setVoiding(null)}
          onVoid={async (reason) => {
            try {
              await voidSalesInvoice(voiding.id, reason);
            } catch (error) {
              throw new Error(salesInvoiceErrorMessage(error, t));
            }
            setNotice(
              t('salesInvoice.notices.voided', {number: voiding.number ?? ''}),
            );
            setVoiding(null);
            reload();
          }}
        />
      )}
    </div>
  );
}
