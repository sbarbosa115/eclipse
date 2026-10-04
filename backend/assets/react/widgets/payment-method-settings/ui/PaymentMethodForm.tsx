import {useState} from 'react';
import {
  AccountPicker,
  accountLabel,
  type AccountChoice,
} from '@/features/pick-account';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import type {
  NewPaymentMethodPayload,
  PaymentMethod,
  PaymentMethodPayload,
} from '../api/paymentMethodSettingsApi';

type FieldName = 'name' | 'kind' | 'account_id';
type Errors = Partial<Record<FieldName, string>>;

interface Props {
  /** The method being edited, or null to create one. */
  method: PaymentMethod | null;
  onClose: () => void;
  onSave: (
    payload: NewPaymentMethodPayload | PaymentMethodPayload,
  ) => Promise<void>;
}

/** The create and edit form of a payment method. The kind is chosen once: contado needs an account, crédito has none. */
export function PaymentMethodForm({method, onClose, onSave}: Props) {
  const {t} = useTranslation();
  const [name, setName] = useState(method?.name ?? '');
  const [kind, setKind] = useState(method?.kind ?? 'cash');
  const [account, setAccount] = useState<AccountChoice>(
    method?.account_id
      ? {
          id: method.account_id,
          text: accountLabel({
            code: method.account_code ?? '',
            name: method.account_name ?? '',
          }),
        }
      : {id: null, text: ''},
  );
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const editing = method !== null;
  const cash = kind === 'cash';

  const submit = async () => {
    const found: Errors = {};
    if (name.trim() === '') found.name = t('paymentMethods.form.required');
    if (cash && account.id === null) {
      found.account_id =
        account.text.trim() === ''
          ? t('paymentMethods.form.accountRequired')
          : t('paymentMethods.form.pickAccount');
    }
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) return;
    const payload: PaymentMethodPayload = {
      name: name.trim(),
      account_id: cash ? account.id : null,
    };
    setBusy(true);
    try {
      await onSave(editing ? payload : {...payload, kind});
    } catch (error) {
      setBusy(false);
      if (error instanceof ApiError && error.status === 422) {
        const body = error.body as {
          violations?: {field: string; message: string}[];
        };
        const server: Errors = {};
        for (const v of body.violations ?? [])
          server[v.field as FieldName] = v.message;
        setErrors(server);
        return;
      }
      setFailure(t('common.errors.unexpected'));
    }
  };

  return (
    <FormModal
      title={
        editing
          ? t('paymentMethods.form.editTitle')
          : t('paymentMethods.form.newTitle')
      }
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={failure}
    >
      <Field
        label={t('paymentMethods.form.name')}
        error={errors.name}
        className="span-2"
      >
        <input
          value={name}
          maxLength={80}
          onChange={(e) => setName(e.target.value)}
        />
      </Field>
      {editing ? (
        <p className="span-2 muted small">{t(`paymentMethods.kind.${kind}`)}</p>
      ) : (
        <Field label={t('paymentMethods.form.kind')} error={errors.kind}>
          <select value={kind} onChange={(e) => setKind(e.target.value)}>
            <option value="cash">{t('paymentMethods.kind.cash')}</option>
            <option value="credit">{t('paymentMethods.kind.credit')}</option>
          </select>
        </Field>
      )}
      {cash ? (
        <Field
          label={t('paymentMethods.form.account')}
          hint={t('paymentMethods.form.accountHint')}
          error={errors.account_id}
          className={editing ? 'span-2' : undefined}
        >
          <AccountPicker
            value={account}
            onChange={(choice) => {
              setAccount(choice);
              setErrors(({account_id: _answered, ...rest}) => rest);
            }}
          />
        </Field>
      ) : (
        <p className="span-2 muted small">
          {t('paymentMethods.form.creditNote')}
        </p>
      )}
    </FormModal>
  );
}
