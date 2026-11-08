import {useCallback, useEffect, useState} from 'react';
import {Link, useNavigate, useSearchParams} from 'react-router-dom';
import {
  canSend,
  canVoid,
  duplicatePurchaseOrder,
  listPurchaseOrders,
  PURCHASE_ORDER_STATUSES,
  purchaseOrderErrorMessage,
  purchaseOrderPdfUrl,
  sendPurchaseOrder,
  voidPurchaseOrder,
  type PurchaseOrderPage,
  type PurchaseOrderSummary,
} from '@/entities/purchase-order';
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
import './purchaseOrderList.css';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: PurchaseOrderPage}
  | {key: string; status: 'failed'};

/**
 * The órdenes de compra (observations PRD §4.8): search by number or supplier, filter by status and date, a page at a time; each row
 * opens, duplicates, downloads its PDF, is sent by e-mail and voided where that is allowed. The filters live in the
 * address, so a reload or a link keeps them.
 */
export function PurchaseOrderList({
  canWrite,
  newPath = 'nueva',
  notice: initialNotice = null,
}: {
  /** The owner and billing users: duplicate, send, void and create. */
  canWrite: boolean;
  /** Where "Crear la primera orden" goes. */
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
  const [voiding, setVoiding] = useState<PurchaseOrderSummary | null>(null);
  const [working, setWorking] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listPurchaseOrders({q, status, from, to, page, per_page: PER_PAGE})
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
    order: PurchaseOrderSummary,
    work: () => Promise<void>,
  ) => {
    setFailure(null);
    setNotice(null);
    setWorking(order.id);
    try {
      await work();
    } catch (error) {
      setFailure(purchaseOrderErrorMessage(error, t));
    } finally {
      setWorking(null);
    }
  };

  const duplicate = (order: PurchaseOrderSummary) =>
    act(order, async () => {
      const copy = await duplicatePurchaseOrder(order.id);
      navigate(copy.id, {
        state: {notice: t('purchaseOrder.notices.duplicated')},
      });
    });

  const send = (order: PurchaseOrderSummary) =>
    act(order, async () => {
      await sendPurchaseOrder(order.id);
      setNotice(t('purchaseOrder.notices.sent', {number: order.number ?? ''}));
    });

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const statusLabel = (value: string) => t(`purchaseOrder.statuses.${value}`);

  return (
    <div className="purchase-order-list">
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => setFilter('q', value.trim())}
        searchPlaceholder={t('purchaseOrder.list.searchPlaceholder')}
        filters={[
          {
            name: 'status',
            label: t('purchaseOrder.list.statusFilter'),
            value: status,
            onChange: (value) => setFilter('status', value),
            options: [
              {value: '', label: t('purchaseOrder.list.allStatuses')},
              ...PURCHASE_ORDER_STATUSES.map((s) => ({
                value: s,
                label: statusLabel(s),
              })),
            ],
          },
        ]}
      >
        <label className="filter-select purchase-order-date">
          <span className="filter-select-label">
            {t('purchaseOrder.list.from')}
          </span>
          <DateInput value={from} onChange={(iso) => setFilter('from', iso)} />
        </label>
        <label className="filter-select purchase-order-date">
          <span className="filter-select-label">
            {t('purchaseOrder.list.to')}
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
              {t('purchaseOrder.list.filteredEmpty')}
            </EmptyState>
          ) : (
            <EmptyState
              action={
                canWrite && (
                  <Link to={newPath} className="btn btn-primary">
                    {t('purchaseOrder.list.emptyAction')}
                  </Link>
                )
              }
            >
              {t('purchaseOrder.list.empty')}
            </EmptyState>
          )
        ) : (
          <>
            <RowLegend
              statuses={PURCHASE_ORDER_STATUSES.map((s) => ({
                value: s,
                label: statusLabel(s),
              }))}
            />
            <DataTable
              columns={[
                t('purchaseOrder.list.columns.number'),
                t('purchaseOrder.list.columns.supplier'),
                t('purchaseOrder.list.columns.date'),
                t('purchaseOrder.list.columns.expected'),
                t('purchaseOrder.list.columns.total'),
              ]}
              rows={shown.items}
              busy={busy}
              renderRow={(order) => (
                <Row
                  key={order.id}
                  status={order.status}
                  label={statusLabel(order.status)}
                >
                  <td>
                    <Link to={order.id}>
                      {order.number ?? t('purchaseOrder.list.draftNumber')}
                    </Link>{' '}
                    <Badge value={order.status}>
                      {statusLabel(order.status)}
                    </Badge>
                  </td>
                  <td>{order.tercero_name}</td>
                  <td>{formatDate(order.issue_date)}</td>
                  <td>
                    {order.expected_date
                      ? formatDate(order.expected_date)
                      : '—'}
                  </td>
                  <td className="num">{formatMoney(order.net_total)}</td>
                  <Actions>
                    <Link
                      to={order.id}
                      className={actionClass(
                        canWrite && order.status === 'draft' ? 'edit' : 'open',
                      )}
                    >
                      {t(
                        canWrite && order.status === 'draft'
                          ? 'purchaseOrder.actions.edit'
                          : 'purchaseOrder.actions.open',
                      )}
                    </Link>
                    <a
                      href={purchaseOrderPdfUrl(order.id)}
                      target="_blank"
                      rel="noreferrer"
                      className={actionClass('open')}
                    >
                      {t('purchaseOrder.actions.pdf')}
                    </a>
                    {canWrite && (
                      <>
                        <ActionButton
                          action="setup"
                          busy={working === order.id}
                          onClick={() => duplicate(order)}
                        >
                          {t('purchaseOrder.actions.duplicate')}
                        </ActionButton>
                        {canSend(order) && (
                          <ActionButton
                            action="confirm"
                            disabled={working === order.id}
                            onClick={() => send(order)}
                          >
                            {t('purchaseOrder.actions.send')}
                          </ActionButton>
                        )}
                        {canVoid(order) && (
                          <ActionButton
                            action="danger"
                            disabled={working === order.id}
                            onClick={() => setVoiding(order)}
                          >
                            {t('purchaseOrder.actions.void')}
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
          title={t('purchaseOrder.void.title', {number: voiding.number ?? ''})}
          body={t('purchaseOrder.void.body')}
          reasonLabel={t('purchaseOrder.void.reason')}
          reasonRequired={t('purchaseOrder.void.reasonRequired')}
          confirmLabel={t('purchaseOrder.void.confirm')}
          onClose={() => setVoiding(null)}
          onVoid={async (reason) => {
            try {
              await voidPurchaseOrder(voiding.id, reason);
            } catch (error) {
              throw new Error(purchaseOrderErrorMessage(error, t));
            }
            setNotice(
              t('purchaseOrder.notices.voided', {number: voiding.number ?? ''}),
            );
            setVoiding(null);
            reload();
          }}
        />
      )}
    </div>
  );
}
