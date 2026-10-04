import {useEffect, useMemo, useState} from 'react';
import {Link, useNavigate} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {AllocatePayment, summarize} from '@/features/allocate-payment';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {todayInColombia} from '@/shared/lib';
import {
  Alert,
  Button,
  Card,
  DateInput,
  EmptyState,
  ErrorState,
  Field,
  Loading,
  PageHeader,
} from '@/shared/ui';
import {
  createCashReceipt,
  listCashMethods,
  listOpenReceivables,
  type OpenReceivable,
  type PaymentMethod,
} from '../api/cashReceiptApi';
import {cashReceiptErrorMessage} from '../lib/errorMessage';
import {canWriteCashReceipts} from '../model/access';
import {
  emptyForm,
  fieldErrorsFrom,
  openItemsOf,
  requestFrom,
  validateForm,
  type FieldErrors,
  type ReceiptForm,
} from '../model/form';
import {ClientPicker} from './ClientPicker';

type Receivables =
  | {clientId: string; status: 'loading'}
  | {clientId: string; status: 'failed'}
  | {clientId: string; status: 'ready'; items: OpenReceivable[]};

/**
 * /recibos-caja/nuevo (§4.9): the client (search from 3 characters), the date, *Dónde ingresa el dinero* (contado
 * methods only), Valor recibido and the client's open receivables, each with an amount and a "pay in full" shortcut,
 * with the running difference; Guardar and Guardar y enviar once it is zero. Saving emits the receipt.
 */
export function NewCashReceipt() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const {session} = useSession();
  const [form, setForm] = useState<ReceiptForm>(() =>
    emptyForm(todayInColombia()),
  );
  const [methods, setMethods] = useState<PaymentMethod[] | null>(null);
  const [receivables, setReceivables] = useState<Receivables | null>(null);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState<'save' | 'send' | null>(null);
  const [reloads, setReloads] = useState(0);

  useEffect(() => {
    let cancelled = false;
    listCashMethods()
      .then((items) => !cancelled && setMethods(items))
      .catch(() => !cancelled && setMethods([]));
    return () => {
      cancelled = true;
    };
  }, []);

  const clientId = form.client?.id ?? null;
  useEffect(() => {
    if (!clientId) return;
    let cancelled = false;
    listOpenReceivables(clientId)
      .then(
        (items) =>
          !cancelled && setReceivables({clientId, status: 'ready', items}),
      )
      .catch(() => !cancelled && setReceivables({clientId, status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [clientId, reloads]);

  const current =
    receivables && receivables.clientId === clientId ? receivables : null;
  const open = current?.status === 'ready' ? current.items : [];
  const items = useMemo(() => openItemsOf(open), [open]);
  const summary = summarize(form.amount, items, form.amounts);
  const ready =
    Object.keys(validateForm(form, t)).length === 0 && summary.balanced;

  if (!canWriteCashReceipts(session)) {
    return (
      <>
        <PageHeader title={t('cashReceipt.form.title')} />
        <ErrorState message={t('cashReceipt.errors.forbidden')} />
      </>
    );
  }

  const update = (patch: Partial<ReceiptForm>) => {
    setForm((f) => ({...f, ...patch}));
    setFailure(null);
  };

  const save = async (send: boolean) => {
    const problems = validateForm(form, t);
    setErrors(problems);
    setRowErrors({});
    setFailure(null);
    if (Object.keys(problems).length > 0 || !summary.balanced) return;
    const request = requestFrom(form, open, send);
    setBusy(send ? 'send' : 'save');
    try {
      const receipt = await createCashReceipt(request);
      navigate(`../${receipt.id}`, {
        state: {
          notice: t(
            send
              ? 'cashReceipt.notices.savedAndSent'
              : 'cashReceipt.notices.saved',
            {number: receipt.number},
          ),
        },
      });
    } catch (error) {
      setBusy(null);
      const violations =
        error instanceof ApiError
          ? ((error.body as {violations?: {field: string; message: string}[]})
              ?.violations ?? [])
          : [];
      const mapped = fieldErrorsFrom(violations, request);
      setErrors(mapped.fields);
      setRowErrors(mapped.rows);
      setFailure(cashReceiptErrorMessage(error, t));
      if (
        error instanceof ApiError &&
        error.code === 'allocation_exceeds_balance'
      ) {
        // Another receipt may have collected part of an invoice meanwhile: show the balances as they are now.
        setReloads((n) => n + 1);
      }
    }
  };

  return (
    <>
      <PageHeader
        title={t('cashReceipt.form.title')}
        actions={
          <Link to=".." relative="path" className="btn btn-ghost">
            {t('cashReceipt.actions.back')}
          </Link>
        }
      />
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <Card>
        <div className="form-grid cash-receipt-form">
          <Field label={t('cashReceipt.form.type')}>
            <input value={t('cashReceipt.form.typeValue')} readOnly />
          </Field>
          <Field label={t('cashReceipt.form.number')}>
            <input value={t('cashReceipt.form.numberPending')} readOnly />
          </Field>
          <Field
            label={t('cashReceipt.form.client')}
            hint={t('cashReceipt.form.clientHint')}
            error={errors.tercero_id}
          >
            <ClientPicker
              value={form.client}
              onChange={(client) => update({client, amounts: {}})}
            />
          </Field>
          <Field label={t('cashReceipt.form.date')} error={errors.receipt_date}>
            <DateInput value={form.date} onChange={(date) => update({date})} />
          </Field>
          <Field
            label={t('cashReceipt.form.method')}
            error={errors.payment_method_id}
          >
            <select
              value={form.methodId}
              onChange={(event) => update({methodId: event.target.value})}
            >
              <option value="">{t('cashReceipt.form.chooseMethod')}</option>
              {(methods ?? []).map((method) => (
                <option key={method.id} value={method.id}>
                  {method.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label={t('cashReceipt.form.amount')} error={errors.amount}>
            <input
              type="text"
              inputMode="decimal"
              placeholder={t('cashReceipt.form.amountPlaceholder')}
              value={form.amount}
              onChange={(event) => update({amount: event.target.value})}
            />
          </Field>
          <Field
            label={t('cashReceipt.form.notes')}
            error={errors.notes}
            optional
            className="field-wide"
          >
            <textarea
              rows={2}
              maxLength={2000}
              value={form.notes}
              onChange={(event) => update({notes: event.target.value})}
            />
          </Field>
        </div>
      </Card>
      <Card title={t('cashReceipt.form.receivables')}>
        <Alert kind="error">{errors.allocations}</Alert>
        {!clientId ? (
          <p className="muted">{t('cashReceipt.form.chooseClientFirst')}</p>
        ) : !current || current.status === 'loading' ? (
          <Loading />
        ) : current.status === 'failed' ? (
          <ErrorState
            message={t('cashReceipt.form.receivablesFailed')}
            onRetry={() => setReloads((n) => n + 1)}
          />
        ) : items.length === 0 ? (
          <EmptyState>{t('cashReceipt.form.noReceivables')}</EmptyState>
        ) : (
          <AllocatePayment
            items={items}
            amounts={form.amounts}
            total={form.amount}
            errors={rowErrors}
            disabled={busy !== null}
            onChange={(amounts) => update({amounts})}
          />
        )}
        <div className="cash-receipt-actions">
          <p className="small muted">{t('cashReceipt.form.saveHint')}</p>
          <Link to=".." relative="path" className="btn btn-ghost">
            {t('common.cancel')}
          </Link>
          <Button
            variant="secondary"
            disabled={!ready || busy !== null}
            busy={busy === 'send'}
            onClick={() => void save(true)}
          >
            {t('cashReceipt.actions.saveAndSend')}
          </Button>
          <Button
            disabled={!ready || busy !== null}
            busy={busy === 'save'}
            onClick={() => void save(false)}
          >
            {t('cashReceipt.actions.save')}
          </Button>
        </div>
      </Card>
    </>
  );
}
