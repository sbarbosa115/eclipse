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
  supplierPaymentPdfUrl,
  getSupplierPayment,
  sendSupplierPayment,
  voidSupplierPayment,
  type SupplierPayment,
} from '../api/supplierPaymentApi';
import {supplierPaymentErrorMessage} from '../lib/errorMessage';
import {canWriteSupplierPayments} from '../model/access';

type Load =
  | {status: 'loading'}
  | {status: 'failed'; message: string}
  | {status: 'ready'; payment: SupplierPayment};

/** /recibos-pago/:id: one payment, read-only (it was emitted when saved), with its PDF, send and Anular (§4.12). */
export function SupplierPaymentView() {
  const {t} = useTranslation();
  const location = useLocation();
  const {id = ''} = useParams();
  const {session} = useSession();
  const writer = canWriteSupplierPayments(session);
  const [load, setLoad] = useState<Load>({status: 'loading'});
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [failure, setFailure] = useState<string | null>(null);
  const [voiding, setVoiding] = useState(false);
  const [sending, setSending] = useState(false);

  useEffect(() => {
    let cancelled = false;
    getSupplierPayment(id)
      .then((payment) => !cancelled && setLoad({status: 'ready', payment}))
      .catch(
        (error: unknown) =>
          !cancelled &&
          setLoad({
            status: 'failed',
            message: supplierPaymentErrorMessage(error, t),
          }),
      );
    return () => {
      cancelled = true;
    };
  }, [id, t]);

  const back = (
    <Link to=".." relative="path" className="btn btn-ghost">
      {t('supplierPayment.actions.back')}
    </Link>
  );

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <>
        <PageHeader title={t('supplierPayment.title')} actions={back} />
        <ErrorState message={load.message} />
      </>
    );
  }

  const payment = load.payment;
  const emitted = payment.status === 'emitted';
  const statusLabel = t(`supplierPayment.statuses.${payment.status}`);

  const send = async () => {
    setFailure(null);
    setNotice(null);
    setSending(true);
    try {
      await sendSupplierPayment(payment.id);
      setNotice(t('supplierPayment.notices.sent', {number: payment.number}));
    } catch (error) {
      setFailure(supplierPaymentErrorMessage(error, t));
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="supplier-payment-view">
      <PageHeader
        title={t('supplierPayment.view.title', {number: payment.number})}
        subtitle={<Badge value={payment.status}>{statusLabel}</Badge>}
        actions={
          <>
            {back}
            <a
              href={supplierPaymentPdfUrl(payment.id)}
              target="_blank"
              rel="noreferrer"
              className={actionClass('open', '', 'md')}
            >
              {t('supplierPayment.actions.downloadPdf')}
            </a>
            {writer && emitted && (
              <>
                <ActionButton
                  action="confirm"
                  size="md"
                  busy={sending}
                  onClick={() => void send()}
                >
                  {t('supplierPayment.actions.send')}
                </ActionButton>
                <ActionButton
                  action="danger"
                  size="md"
                  onClick={() => setVoiding(true)}
                >
                  {t('supplierPayment.actions.void')}
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
          ? t('supplierPayment.view.readOnly')
          : t('supplierPayment.view.voidedInfo', {
              date: formatDate(payment.voided_at),
              reason: payment.void_reason ?? '',
            })}
      </Alert>
      <Card>
        <dl className="supplier-payment-details">
          <div>
            <dt>{t('supplierPayment.form.supplier')}</dt>
            <dd>{payment.tercero_name}</dd>
          </div>
          <div>
            <dt>{t('supplierPayment.form.date')}</dt>
            <dd>{formatDate(payment.receipt_date)}</dd>
          </div>
          <div>
            <dt>{t('supplierPayment.form.method')}</dt>
            <dd>{payment.method_name}</dd>
          </div>
          <div>
            <dt>{t('supplierPayment.form.amount')}</dt>
            <dd>
              <strong>{formatMoney(payment.amount)}</strong>
            </dd>
          </div>
          {payment.notes && (
            <div>
              <dt>{t('supplierPayment.form.notes')}</dt>
              <dd>{payment.notes}</dd>
            </div>
          )}
        </dl>
      </Card>
      <Card title={t('supplierPayment.view.allocations')}>
        <DataTable
          actions={false}
          columns={[
            t('supplierPayment.view.invoice'),
            t('supplierPayment.view.applied'),
          ]}
          rows={payment.allocations}
          renderRow={(allocation) => (
            <tr key={allocation.id}>
              <td>
                <Link to={`/facturas-compra/${allocation.invoice_id}`}>
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
          title={t('supplierPayment.void.title', {number: payment.number})}
          body={t('supplierPayment.void.body')}
          reasonLabel={t('supplierPayment.void.reason')}
          reasonRequired={t('supplierPayment.void.reasonRequired')}
          confirmLabel={t('supplierPayment.void.confirm')}
          onClose={() => setVoiding(false)}
          onVoid={async (reason) => {
            let voided: SupplierPayment;
            try {
              voided = await voidSupplierPayment(payment.id, reason);
            } catch (error) {
              throw new Error(supplierPaymentErrorMessage(error, t));
            }
            setVoiding(false);
            setLoad({status: 'ready', payment: voided});
            setNotice(
              t('supplierPayment.notices.voided', {number: voided.number}),
            );
          }}
        />
      )}
    </div>
  );
}
