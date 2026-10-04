import {useCallback, useEffect, useState} from 'react';
import {Link, useLocation, useSearchParams} from 'react-router-dom';
import {useSession} from '@/entities/session';
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
  PageHeader,
  Pager,
  Row,
  RowLegend,
} from '@/shared/ui';
import {
  supplierPaymentPdfUrl,
  listSupplierPayments,
  PAYMENT_STATUSES,
  sendSupplierPayment,
  voidSupplierPayment,
  type SupplierPaymentPage,
  type SupplierPaymentSummary,
} from '../api/supplierPaymentApi';
import {supplierPaymentErrorMessage} from '../lib/errorMessage';
import {canWriteSupplierPayments} from '../model/access';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: SupplierPaymentPage}
  | {key: string; status: 'failed'};

/**
 * /recibos-pago (§4.15): search by number or supplier, filter by status and date, a page at a time; each row opens,
 * downloads its PDF, is sent by e-mail and voided. The filters live in the address, so a reload or a link keeps them.
 */
export function SupplierPaymentsList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWriteSupplierPayments(session);
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const status = params.get('status') ?? '';
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);

  const [result, setResult] = useState<Result | null>(null);
  const [reloads, setReloads] = useState(0);
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [failure, setFailure] = useState<string | null>(null);
  const [voiding, setVoiding] = useState<SupplierPaymentSummary | null>(null);
  const [working, setWorking] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listSupplierPayments({q, status, from, to, page, per_page: PER_PAGE})
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

  const send = async (payment: SupplierPaymentSummary) => {
    setFailure(null);
    setNotice(null);
    setWorking(payment.id);
    try {
      await sendSupplierPayment(payment.id);
      setNotice(t('supplierPayment.notices.sent', {number: payment.number}));
    } catch (error) {
      setFailure(supplierPaymentErrorMessage(error, t));
    } finally {
      setWorking(null);
    }
  };

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const statusLabel = (value: string) => t(`supplierPayment.statuses.${value}`);

  return (
    <>
      <PageHeader
        title={t('supplierPayment.title')}
        subtitle={t('supplierPayment.subtitle')}
        actions={
          writer && (
            <Link to="nuevo" className="btn btn-primary">
              {t('supplierPayment.new')}
            </Link>
          )
        }
      />
      <div className="supplier-payment-list">
        <Alert kind="success" onDismiss={() => setNotice(null)}>
          {notice}
        </Alert>
        <Alert kind="error" onDismiss={() => setFailure(null)}>
          {failure}
        </Alert>
        <FilterBar
          search={q}
          onSearch={(value) => setFilter('q', value.trim())}
          searchPlaceholder={t('supplierPayment.list.searchPlaceholder')}
          filters={[
            {
              name: 'status',
              label: t('supplierPayment.list.statusFilter'),
              value: status,
              onChange: (value) => setFilter('status', value),
              options: [
                {value: '', label: t('supplierPayment.list.allStatuses')},
                ...PAYMENT_STATUSES.map((s) => ({
                  value: s,
                  label: statusLabel(s),
                })),
              ],
            },
          ]}
        >
          <label className="filter-select supplier-payment-date">
            <span className="filter-select-label">
              {t('supplierPayment.list.from')}
            </span>
            <DateInput
              value={from}
              onChange={(iso) => setFilter('from', iso)}
            />
          </label>
          <label className="filter-select supplier-payment-date">
            <span className="filter-select-label">
              {t('supplierPayment.list.to')}
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
                {t('supplierPayment.list.filteredEmpty')}
              </EmptyState>
            ) : (
              <EmptyState
                action={
                  writer && (
                    <Link to="nuevo" className="btn btn-primary">
                      {t('supplierPayment.list.emptyAction')}
                    </Link>
                  )
                }
              >
                {t('supplierPayment.list.empty')}
              </EmptyState>
            )
          ) : (
            <>
              <RowLegend
                statuses={PAYMENT_STATUSES.map((s) => ({
                  value: s,
                  label: statusLabel(s),
                }))}
              />
              <DataTable
                columns={[
                  t('supplierPayment.list.columns.number'),
                  t('supplierPayment.list.columns.supplier'),
                  t('supplierPayment.list.columns.date'),
                  t('supplierPayment.list.columns.method'),
                  t('supplierPayment.list.columns.invoices'),
                  t('supplierPayment.list.columns.amount'),
                ]}
                rows={shown.items}
                busy={busy}
                renderRow={(payment) => (
                  <Row
                    key={payment.id}
                    status={payment.status}
                    label={statusLabel(payment.status)}
                  >
                    <td>
                      <Link to={payment.id}>{payment.number}</Link>{' '}
                      <Badge value={payment.status}>
                        {statusLabel(payment.status)}
                      </Badge>
                    </td>
                    <td>{payment.tercero_name}</td>
                    <td>{formatDate(payment.receipt_date)}</td>
                    <td>{payment.method_name}</td>
                    <td>{payment.invoice_numbers.join(', ')}</td>
                    <td className="num">{formatMoney(payment.amount)}</td>
                    <Actions>
                      <Link to={payment.id} className={actionClass('open')}>
                        {t('supplierPayment.actions.open')}
                      </Link>
                      <a
                        href={supplierPaymentPdfUrl(payment.id)}
                        target="_blank"
                        rel="noreferrer"
                        className={actionClass('open')}
                      >
                        {t('supplierPayment.actions.pdf')}
                      </a>
                      {writer && payment.status === 'emitted' && (
                        <>
                          <ActionButton
                            action="confirm"
                            busy={working === payment.id}
                            onClick={() => void send(payment)}
                          >
                            {t('supplierPayment.actions.send')}
                          </ActionButton>
                          <ActionButton
                            action="danger"
                            disabled={working === payment.id}
                            onClick={() => setVoiding(payment)}
                          >
                            {t('supplierPayment.actions.void')}
                          </ActionButton>
                        </>
                      )}
                    </Actions>
                  </Row>
                )}
              />
              <Pager
                data={shown}
                onPage={(p) => setFilter('page', String(p))}
              />
            </>
          ))}
        {voiding && (
          <VoidDocumentModal
            title={t('supplierPayment.void.title', {number: voiding.number})}
            body={t('supplierPayment.void.body')}
            reasonLabel={t('supplierPayment.void.reason')}
            reasonRequired={t('supplierPayment.void.reasonRequired')}
            confirmLabel={t('supplierPayment.void.confirm')}
            onClose={() => setVoiding(null)}
            onVoid={async (reason) => {
              try {
                await voidSupplierPayment(voiding.id, reason);
              } catch (error) {
                throw new Error(supplierPaymentErrorMessage(error, t));
              }
              setNotice(
                t('supplierPayment.notices.voided', {number: voiding.number}),
              );
              setVoiding(null);
              reload();
            }}
          />
        )}
      </div>
    </>
  );
}
