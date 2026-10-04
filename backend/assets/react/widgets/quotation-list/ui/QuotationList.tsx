import {useCallback, useEffect, useState} from 'react';
import {Link, useNavigate, useSearchParams} from 'react-router-dom';
import {
  canSend,
  canVoid,
  duplicateQuotation,
  listQuotations,
  QUOTATION_STATUSES,
  quotationErrorMessage,
  quotationPdfUrl,
  sendQuotation,
  statusTone,
  voidQuotation,
  type QuotationPage,
  type QuotationSummary,
} from '@/entities/quotation';
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
import './quotationList.css';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: QuotationPage}
  | {key: string; status: 'failed'};

/**
 * The cotizaciones (§4.15): search by number or client, filter by status and date, a page at a time; each row
 * opens, duplicates, downloads its PDF, is sent by e-mail and voided where that is allowed. The filters live in the
 * address, so a reload or a link keeps them.
 */
export function QuotationList({
  canWrite,
  newPath = 'nueva',
  notice: initialNotice = null,
}: {
  /** The owner and billing users: duplicate, send, void and create. */
  canWrite: boolean;
  /** Where "Crear la primera cotización" goes. */
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
  const [voiding, setVoiding] = useState<QuotationSummary | null>(null);
  const [working, setWorking] = useState<string | null>(null);

  const key = `${q}|${status}|${from}|${to}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    listQuotations({q, status, from, to, page, per_page: PER_PAGE})
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
    quotation: QuotationSummary,
    work: () => Promise<void>,
  ) => {
    setFailure(null);
    setNotice(null);
    setWorking(quotation.id);
    try {
      await work();
    } catch (error) {
      setFailure(quotationErrorMessage(error, t));
    } finally {
      setWorking(null);
    }
  };

  const duplicate = (quotation: QuotationSummary) =>
    act(quotation, async () => {
      const copy = await duplicateQuotation(quotation.id);
      navigate(copy.id, {
        state: {notice: t('quotation.notices.duplicated')},
      });
    });

  const send = (quotation: QuotationSummary) =>
    act(quotation, async () => {
      await sendQuotation(quotation.id);
      setNotice(t('quotation.notices.sent', {number: quotation.number ?? ''}));
    });

  const filtered = q !== '' || status !== '' || from !== '' || to !== '';
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const statusLabel = (value: string) => t(`quotation.statuses.${value}`);

  return (
    <div className="quotation-list">
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => setFilter('q', value.trim())}
        searchPlaceholder={t('quotation.list.searchPlaceholder')}
        filters={[
          {
            name: 'status',
            label: t('quotation.list.statusFilter'),
            value: status,
            onChange: (value) => setFilter('status', value),
            options: [
              {value: '', label: t('quotation.list.allStatuses')},
              ...QUOTATION_STATUSES.map((s) => ({
                value: s,
                label: statusLabel(s),
              })),
            ],
          },
        ]}
      >
        <label className="filter-select quotation-date">
          <span className="filter-select-label">
            {t('quotation.list.from')}
          </span>
          <DateInput value={from} onChange={(iso) => setFilter('from', iso)} />
        </label>
        <label className="filter-select quotation-date">
          <span className="filter-select-label">{t('quotation.list.to')}</span>
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
              {t('quotation.list.filteredEmpty')}
            </EmptyState>
          ) : (
            <EmptyState
              action={
                canWrite && (
                  <Link to={newPath} className="btn btn-primary">
                    {t('quotation.list.emptyAction')}
                  </Link>
                )
              }
            >
              {t('quotation.list.empty')}
            </EmptyState>
          )
        ) : (
          <>
            <RowLegend
              statuses={QUOTATION_STATUSES.map((s) => ({
                value: statusTone(s),
                label: statusLabel(s),
              }))}
            />
            <DataTable
              columns={[
                t('quotation.list.columns.number'),
                t('quotation.list.columns.client'),
                t('quotation.list.columns.date'),
                t('quotation.list.columns.expiry'),
                t('quotation.list.columns.total'),
              ]}
              rows={shown.items}
              busy={busy}
              renderRow={(quotation) => (
                <Row
                  key={quotation.id}
                  status={statusTone(quotation.status)}
                  label={statusLabel(quotation.status)}
                >
                  <td>
                    <Link to={quotation.id}>
                      {quotation.number ?? t('quotation.list.draftNumber')}
                    </Link>{' '}
                    <Badge value={statusTone(quotation.status)}>
                      {statusLabel(quotation.status)}
                    </Badge>
                  </td>
                  <td>{quotation.tercero_name}</td>
                  <td>{formatDate(quotation.issue_date)}</td>
                  <td>{formatDate(quotation.expiry_date)}</td>
                  <td className="num">{formatMoney(quotation.net_total)}</td>
                  <Actions>
                    <Link
                      to={quotation.id}
                      className={actionClass(
                        canWrite && quotation.status === 'draft'
                          ? 'edit'
                          : 'open',
                      )}
                    >
                      {t(
                        canWrite && quotation.status === 'draft'
                          ? 'quotation.actions.edit'
                          : 'quotation.actions.open',
                      )}
                    </Link>
                    <a
                      href={quotationPdfUrl(quotation.id)}
                      target="_blank"
                      rel="noreferrer"
                      className={actionClass('open')}
                    >
                      {t('quotation.actions.pdf')}
                    </a>
                    {canWrite && (
                      <>
                        <ActionButton
                          action="setup"
                          busy={working === quotation.id}
                          onClick={() => duplicate(quotation)}
                        >
                          {t('quotation.actions.duplicate')}
                        </ActionButton>
                        {canSend(quotation) && (
                          <ActionButton
                            action="confirm"
                            disabled={working === quotation.id}
                            onClick={() => send(quotation)}
                          >
                            {t('quotation.actions.send')}
                          </ActionButton>
                        )}
                        {canVoid(quotation) && (
                          <ActionButton
                            action="danger"
                            disabled={working === quotation.id}
                            onClick={() => setVoiding(quotation)}
                          >
                            {t('quotation.actions.void')}
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
          title={t('quotation.void.title', {number: voiding.number ?? ''})}
          body={t('quotation.void.body')}
          reasonLabel={t('quotation.void.reason')}
          reasonRequired={t('quotation.void.reasonRequired')}
          confirmLabel={t('quotation.void.confirm')}
          onClose={() => setVoiding(null)}
          onVoid={async (reason) => {
            try {
              await voidQuotation(voiding.id, reason);
            } catch (error) {
              throw new Error(quotationErrorMessage(error, t));
            }
            setNotice(
              t('quotation.notices.voided', {number: voiding.number ?? ''}),
            );
            setVoiding(null);
            reload();
          }}
        />
      )}
    </div>
  );
}
