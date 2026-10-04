import {useEffect, useState} from 'react';
import {Link, useLocation, useParams} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {VoidDocumentModal} from '@/features/void-document';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {
  actionClass,
  ActionButton,
  Alert,
  Badge,
  Card,
  DataTable,
  ErrorState,
  Loading,
  PageHeader,
} from '@/shared/ui';
import {
  cashReceiptPdfUrl,
  getCashReceipt,
  sendCashReceipt,
  voidCashReceipt,
  type CashReceipt,
} from '../api/cashReceiptApi';
import {cashReceiptErrorMessage} from '../lib/errorMessage';
import {canWriteCashReceipts} from '../model/access';

type Load =
  | {status: 'loading'}
  | {status: 'failed'; message: string}
  | {status: 'ready'; receipt: CashReceipt};

/** /recibos-caja/:id: one receipt, read-only (it was emitted when saved), with its PDF, send and Anular (§4.12). */
export function CashReceiptView() {
  const {t} = useTranslation();
  const location = useLocation();
  const {id = ''} = useParams();
  const {session} = useSession();
  const writer = canWriteCashReceipts(session);
  const [load, setLoad] = useState<Load>({status: 'loading'});
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [failure, setFailure] = useState<string | null>(null);
  const [voiding, setVoiding] = useState(false);
  const [sending, setSending] = useState(false);

  useEffect(() => {
    let cancelled = false;
    getCashReceipt(id)
      .then((receipt) => !cancelled && setLoad({status: 'ready', receipt}))
      .catch(
        (error: unknown) =>
          !cancelled &&
          setLoad({
            status: 'failed',
            message: cashReceiptErrorMessage(error, t),
          }),
      );
    return () => {
      cancelled = true;
    };
  }, [id, t]);

  const back = (
    <Link to=".." relative="path" className="btn btn-ghost">
      {t('cashReceipt.actions.back')}
    </Link>
  );

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <>
        <PageHeader title={t('cashReceipt.title')} actions={back} />
        <ErrorState message={load.message} />
      </>
    );
  }

  const receipt = load.receipt;
  const emitted = receipt.status === 'emitted';
  const statusLabel = t(`cashReceipt.statuses.${receipt.status}`);

  const send = async () => {
    setFailure(null);
    setNotice(null);
    setSending(true);
    try {
      await sendCashReceipt(receipt.id);
      setNotice(t('cashReceipt.notices.sent', {number: receipt.number}));
    } catch (error) {
      setFailure(cashReceiptErrorMessage(error, t));
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="cash-receipt-view">
      <PageHeader
        title={t('cashReceipt.view.title', {number: receipt.number})}
        subtitle={<Badge value={receipt.status}>{statusLabel}</Badge>}
        actions={
          <>
            {back}
            <a
              href={cashReceiptPdfUrl(receipt.id)}
              target="_blank"
              rel="noreferrer"
              className={actionClass('open', '', 'md')}
            >
              {t('cashReceipt.actions.downloadPdf')}
            </a>
            {writer && emitted && (
              <>
                <ActionButton
                  action="confirm"
                  size="md"
                  busy={sending}
                  onClick={() => void send()}
                >
                  {t('cashReceipt.actions.send')}
                </ActionButton>
                <ActionButton
                  action="danger"
                  size="md"
                  onClick={() => setVoiding(true)}
                >
                  {t('cashReceipt.actions.void')}
                </ActionButton>
              </>
            )}
          </>
        }
      />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <Alert kind="info">
        {emitted
          ? t('cashReceipt.view.readOnly')
          : t('cashReceipt.view.voidedInfo', {
              date: formatDate(receipt.voided_at),
              reason: receipt.void_reason ?? '',
            })}
      </Alert>
      <Card>
        <dl className="cash-receipt-details">
          <div>
            <dt>{t('cashReceipt.form.client')}</dt>
            <dd>{receipt.tercero_name}</dd>
          </div>
          <div>
            <dt>{t('cashReceipt.form.date')}</dt>
            <dd>{formatDate(receipt.receipt_date)}</dd>
          </div>
          <div>
            <dt>{t('cashReceipt.form.method')}</dt>
            <dd>{receipt.method_name}</dd>
          </div>
          <div>
            <dt>{t('cashReceipt.form.amount')}</dt>
            <dd>
              <strong>{formatMoney(receipt.amount)}</strong>
            </dd>
          </div>
          {receipt.notes && (
            <div>
              <dt>{t('cashReceipt.form.notes')}</dt>
              <dd>{receipt.notes}</dd>
            </div>
          )}
        </dl>
      </Card>
      <Card title={t('cashReceipt.view.allocations')}>
        <DataTable
          actions={false}
          columns={[
            t('cashReceipt.view.invoice'),
            t('cashReceipt.view.applied'),
          ]}
          rows={receipt.allocations}
          renderRow={(allocation) => (
            <tr key={allocation.id}>
              <td>
                <Link to={`/facturas-venta/${allocation.invoice_id}`}>
                  {allocation.invoice_number}
                </Link>
              </td>
              <td className="num">{formatMoney(allocation.amount)}</td>
            </tr>
          )}
        />
      </Card>
      {voiding && (
        <VoidDocumentModal
          title={t('cashReceipt.void.title', {number: receipt.number})}
          body={t('cashReceipt.void.body')}
          reasonLabel={t('cashReceipt.void.reason')}
          reasonRequired={t('cashReceipt.void.reasonRequired')}
          confirmLabel={t('cashReceipt.void.confirm')}
          onClose={() => setVoiding(false)}
          onVoid={async (reason) => {
            let voided: CashReceipt;
            try {
              voided = await voidCashReceipt(receipt.id, reason);
            } catch (error) {
              throw new Error(cashReceiptErrorMessage(error, t));
            }
            setVoiding(false);
            setLoad({status: 'ready', receipt: voided});
            setNotice(t('cashReceipt.notices.voided', {number: voided.number}));
          }}
        />
      )}
    </div>
  );
}
