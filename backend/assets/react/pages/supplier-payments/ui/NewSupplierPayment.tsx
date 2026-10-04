import {useEffect, useMemo, useState} from 'react';
import {Link, useNavigate} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {TerceroPicker} from '@/entities/tercero';
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
  createSupplierPayment,
  listCashMethods,
  listOpenPayables,
  type OpenPayable,
  type PaymentMethod,
} from '../api/supplierPaymentApi';
import {supplierPaymentErrorMessage} from '../lib/errorMessage';
import {canWriteSupplierPayments} from '../model/access';
import {
  emptyForm,
  fieldErrorsFrom,
  openItemsOf,
  requestFrom,
  validateForm,
  type FieldErrors,
  type PaymentForm,
} from '../model/form';

type Payables =
  | {supplierId: string; status: 'loading'}
  | {supplierId: string; status: 'failed'}
  | {supplierId: string; status: 'ready'; items: OpenPayable[]};

/**
 * /recibos-pago/nuevo (§4.11): the supplier (search from 3 characters), the date, *De dónde sale el dinero* (contado
 * methods only), Valor pagado and the supplier's open payables, each with an amount and a "pay in full" shortcut,
 * with the running difference; Guardar and Guardar y enviar once it is zero. Saving emits the payment.
 */
export function NewSupplierPayment() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const {session} = useSession();
  const [form, setForm] = useState<PaymentForm>(() =>
    emptyForm(todayInColombia()),
  );
  const [methods, setMethods] = useState<PaymentMethod[] | null>(null);
  const [payables, setPayables] = useState<Payables | null>(null);
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

  const supplierId = form.supplier?.id ?? null;
  useEffect(() => {
    if (!supplierId) return;
    let cancelled = false;
    listOpenPayables(supplierId)
      .then(
        (items) =>
          !cancelled && setPayables({supplierId, status: 'ready', items}),
      )
      .catch(() => !cancelled && setPayables({supplierId, status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [supplierId, reloads]);

  const current =
    payables && payables.supplierId === supplierId ? payables : null;
  const open = useMemo(
    () => (current?.status === 'ready' ? current.items : []),
    [current],
  );
  const items = useMemo(() => openItemsOf(open), [open]);
  const summary = summarize(form.amount, items, form.amounts);
  const ready =
    Object.keys(validateForm(form, t)).length === 0 && summary.balanced;

  if (!canWriteSupplierPayments(session)) {
    return (
      <>
        <PageHeader title={t('supplierPayment.form.title')} />
        <ErrorState message={t('supplierPayment.errors.forbidden')} />
      </>
    );
  }

  const update = (patch: Partial<PaymentForm>) => {
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
      const payment = await createSupplierPayment(request);
      navigate(`../${payment.id}`, {
        state: {
          notice: t(
            send
              ? 'supplierPayment.notices.savedAndSent'
              : 'supplierPayment.notices.saved',
            {number: payment.number},
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
      setFailure(supplierPaymentErrorMessage(error, t));
      if (
        error instanceof ApiError &&
        error.code === 'allocation_exceeds_balance'
      ) {
        // Another payment may have paid part of an invoice meanwhile: show the balances as they are now.
        setReloads((n) => n + 1);
      }
    }
  };

  return (
    <>
      <PageHeader
        title={t('supplierPayment.form.title')}
        actions={
          <Link to=".." relative="path" className="btn btn-ghost">
            {t('supplierPayment.actions.back')}
          </Link>
        }
      />
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <Card>
        <div className="form-grid supplier-payment-form">
          <Field label={t('supplierPayment.form.type')}>
            <input value={t('supplierPayment.form.typeValue')} readOnly />
          </Field>
          <Field label={t('supplierPayment.form.number')}>
            <input value={t('supplierPayment.form.numberPending')} readOnly />
          </Field>
          <Field
            label={t('supplierPayment.form.supplier')}
            hint={t('supplierPayment.form.supplierHint')}
            error={errors.tercero_id}
          >
            <TerceroPicker
              role="proveedor"
              value={form.supplier}
              onChange={(supplier) => update({supplier, amounts: {}})}
              labels={{
                placeholder: t('supplierPayment.form.supplierPlaceholder'),
                minChars: (count) =>
                  t('supplierPayment.form.search.minChars', {count}),
                searching: t('supplierPayment.form.search.searching'),
                none: t('supplierPayment.form.search.none'),
                failed: t('supplierPayment.form.search.failed'),
              }}
            />
          </Field>
          <Field
            label={t('supplierPayment.form.date')}
            error={errors.receipt_date}
          >
            <DateInput value={form.date} onChange={(date) => update({date})} />
          </Field>
          <Field
            label={t('supplierPayment.form.method')}
            error={errors.payment_method_id}
          >
            <select
              value={form.methodId}
              onChange={(event) => update({methodId: event.target.value})}
            >
              <option value="">{t('supplierPayment.form.chooseMethod')}</option>
              {(methods ?? []).map((method) => (
                <option key={method.id} value={method.id}>
                  {method.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label={t('supplierPayment.form.amount')} error={errors.amount}>
            <input
              type="text"
              inputMode="decimal"
              placeholder={t('supplierPayment.form.amountPlaceholder')}
              value={form.amount}
              onChange={(event) => update({amount: event.target.value})}
            />
          </Field>
          <Field
            label={t('supplierPayment.form.notes')}
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
      <Card title={t('supplierPayment.form.payables')}>
        <Alert kind="error">{errors.allocations}</Alert>
        {!supplierId ? (
          <p className="muted">
            {t('supplierPayment.form.chooseSupplierFirst')}
          </p>
        ) : !current || current.status === 'loading' ? (
          <Loading />
        ) : current.status === 'failed' ? (
          <ErrorState
            message={t('supplierPayment.form.payablesFailed')}
            onRetry={() => setReloads((n) => n + 1)}
          />
        ) : items.length === 0 ? (
          <EmptyState>{t('supplierPayment.form.noPayables')}</EmptyState>
        ) : (
          <AllocatePayment
            labels="supplierPayment.allocate"
            items={items}
            amounts={form.amounts}
            total={form.amount}
            errors={rowErrors}
            disabled={busy !== null}
            onChange={(amounts) => update({amounts})}
          />
        )}
        <div className="supplier-payment-actions">
          <p className="small muted">{t('supplierPayment.form.saveHint')}</p>
          <Link to=".." relative="path" className="btn btn-ghost">
            {t('common.cancel')}
          </Link>
          <Button
            variant="secondary"
            disabled={!ready || busy !== null}
            busy={busy === 'send'}
            onClick={() => void save(true)}
          >
            {t('supplierPayment.actions.saveAndSend')}
          </Button>
          <Button
            disabled={!ready || busy !== null}
            busy={busy === 'save'}
            onClick={() => void save(false)}
          >
            {t('supplierPayment.actions.save')}
          </Button>
        </div>
      </Card>
    </>
  );
}
