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
  cashReceiptPdfUrl,
  listCashReceipts,
  RECEIPT_STATUSES,
  sendCashReceipt,
  voidCashReceipt,
  type CashReceiptPage,
  type CashReceiptSummary,
} from '../api/cashReceiptApi';
import {cashReceiptErrorMessage} from '../lib/errorMessage';
import {canWriteCashReceipts} from '../model/access';
import {receiptTone} from '../model/status';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: CashReceiptPage}
  | {key: string; status: 'failed'};

/**
 * /recibos-caja (§4.15): search by number or client, filter by status and date, a page at a time; each row opens,
 * downloads its PDF, is sent by e-mail and voided. The filters live in the address, so a reload or a link keeps them.
 */
export function CashReceiptsList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWriteCashReceipts(session);
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
  const [voiding, setVoiding] = useState<CashReceiptSummary | null>(null);
  const [working, setWorking] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listCashReceipts({q, status, from, to, page, per_page: PER_PAGE})
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

  const send = async (receipt: CashReceiptSummary) => {
    setFailure(null);
    setNotice(null);
    setWorking(receipt.id);
    try {
      await sendCashReceipt(receipt.id);
      setNotice(t('cashReceipt.notices.sent', {number: receipt.number}));
    } catch (error) {
      setFailure(cashReceiptErrorMessage(error, t));
    } finally {
      setWorking(null);
    }
  };

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const statusLabel = (value: string) => t(`cashReceipt.statuses.${value}`);

  return (
    <>
      <PageHeader
        title={t('cashReceipt.title')}
        subtitle={t('cashReceipt.subtitle')}
        actions={
          writer && (
            <Link to="nuevo" className="btn btn-primary">
              {t('cashReceipt.new')}
            </Link>
          )
        }
      />
      <div className="cash-receipt-list">
        <Alert kind="success" onDismiss={() => setNotice(null)}>
          {notice}
        </Alert>
        <Alert kind="error" onDismiss={() => setFailure(null)}>
          {failure}
        </Alert>
        <FilterBar
          search={q}
          onSearch={(value) => setFilter('q', value.trim())}
          searchPlaceholder={t('cashReceipt.list.searchPlaceholder')}
          filters={[
            {
              name: 'status',
              label: t('cashReceipt.list.statusFilter'),
              value: status,
              onChange: (value) => setFilter('status', value),
              options: [
                {value: '', label: t('cashReceipt.list.allStatuses')},
                ...RECEIPT_STATUSES.map((s) => ({
                  value: s,
                  label: statusLabel(s),
                })),
              ],
            },
          ]}
        >
          <label className="filter-select cash-receipt-date">
            <span className="filter-select-label">
              {t('cashReceipt.list.from')}
            </span>
            <DateInput
              value={from}
              onChange={(iso) => setFilter('from', iso)}
            />
          </label>
          <label className="filter-select cash-receipt-date">
            <span className="filter-select-label">
              {t('cashReceipt.list.to')}
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
                {t('cashReceipt.list.filteredEmpty')}
              </EmptyState>
            ) : (
              <EmptyState
                action={
                  writer && (
                    <Link to="nuevo" className="btn btn-primary">
                      {t('cashReceipt.list.emptyAction')}
                    </Link>
                  )
                }
              >
                {t('cashReceipt.list.empty')}
              </EmptyState>
            )
          ) : (
            <>
              <RowLegend
                statuses={RECEIPT_STATUSES.map((s) => ({
                  value: receiptTone(s),
                  label: statusLabel(s),
                }))}
              />
              <DataTable
                columns={[
                  t('cashReceipt.list.columns.number'),
                  t('cashReceipt.list.columns.client'),
                  t('cashReceipt.list.columns.date'),
                  t('cashReceipt.list.columns.method'),
                  t('cashReceipt.list.columns.invoices'),
                  t('cashReceipt.list.columns.amount'),
                ]}
                rows={shown.items}
                busy={busy}
                renderRow={(receipt) => (
                  <Row
                    key={receipt.id}
                    status={receiptTone(receipt.status)}
                    label={statusLabel(receipt.status)}
                  >
                    <td>
                      <Link to={receipt.id}>{receipt.number}</Link>{' '}
                      <Badge value={receiptTone(receipt.status)}>
                        {statusLabel(receipt.status)}
                      </Badge>
                    </td>
                    <td>{receipt.tercero_name}</td>
                    <td>{formatDate(receipt.receipt_date)}</td>
                    <td>{receipt.method_name}</td>
                    <td>{receipt.invoice_numbers.join(', ')}</td>
                    <td className="num">{formatMoney(receipt.amount)}</td>
                    <Actions>
                      <Link to={receipt.id} className={actionClass('open')}>
                        {t('cashReceipt.actions.open')}
                      </Link>
                      <a
                        href={cashReceiptPdfUrl(receipt.id)}
                        target="_blank"
                        rel="noreferrer"
                        className={actionClass('open')}
                      >
                        {t('cashReceipt.actions.pdf')}
                      </a>
                      {writer && receipt.status === 'emitted' && (
                        <>
                          <ActionButton
                            action="confirm"
                            busy={working === receipt.id}
                            onClick={() => void send(receipt)}
                          >
                            {t('cashReceipt.actions.send')}
                          </ActionButton>
                          <ActionButton
                            action="danger"
                            disabled={working === receipt.id}
                            onClick={() => setVoiding(receipt)}
                          >
                            {t('cashReceipt.actions.void')}
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
            title={t('cashReceipt.void.title', {number: voiding.number})}
            body={t('cashReceipt.void.body')}
            reasonLabel={t('cashReceipt.void.reason')}
            reasonRequired={t('cashReceipt.void.reasonRequired')}
            confirmLabel={t('cashReceipt.void.confirm')}
            onClose={() => setVoiding(null)}
            onVoid={async (reason) => {
              try {
                await voidCashReceipt(voiding.id, reason);
              } catch (error) {
                throw new Error(cashReceiptErrorMessage(error, t));
              }
              setNotice(
                t('cashReceipt.notices.voided', {number: voiding.number}),
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
