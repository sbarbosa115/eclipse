import {useState} from 'react';
import {
  AccountPicker,
  accountLabel,
  type AccountChoice,
} from '@/features/pick-account';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {DateInput, Field, FormModal, MoneyInput} from '@/shared/ui';
import type {NewTaxPayload, Tax, TaxPayload} from '../api/taxSettingsApi';

const KINDS: Record<string, readonly string[]> = {
  charge: ['iva', 'impoconsumo'],
  withholding: ['retefuente', 'reteiva', 'reteica'],
};

type FieldName =
  | 'name'
  | 'tax_class'
  | 'kind'
  | 'calculation'
  | 'rate'
  | 'sales_account_id'
  | 'purchase_account_id'
  | 'valid_from'
  | 'valid_to';
type Errors = Partial<Record<FieldName, string>>;

interface Props {
  /** The tax being edited, or null to create one. */
  tax: Tax | null;
  onClose: () => void;
  onSave: (payload: NewTaxPayload | TaxPayload) => Promise<void>;
}

const choiceOf = (
  id: string | null | undefined,
  code: string | null | undefined,
  name: string | null | undefined,
): AccountChoice =>
  id
    ? {id, text: accountLabel({code: code ?? '', name: name ?? ''})}
    : {id: null, text: ''};

/** The create and edit form of a tax. The class and kind are chosen once: they decide how documents post it. */
export function TaxForm({tax, onClose, onSave}: Props) {
  const {t} = useTranslation();
  const [name, setName] = useState(tax?.name ?? '');
  const [taxClass, setTaxClass] = useState(tax?.tax_class ?? 'charge');
  const [kind, setKind] = useState(tax?.kind ?? 'iva');
  const [calculation, setCalculation] = useState(
    tax?.calculation ?? 'percentage',
  );
  const [rate, setRate] = useState(tax?.rate ?? '');
  const [sales, setSales] = useState(
    choiceOf(
      tax?.sales_account_id,
      tax?.sales_account_code,
      tax?.sales_account_name,
    ),
  );
  const [purchases, setPurchases] = useState(
    choiceOf(
      tax?.purchase_account_id,
      tax?.purchase_account_code,
      tax?.purchase_account_name,
    ),
  );
  const [validFrom, setValidFrom] = useState(tax?.valid_from ?? '');
  const [validTo, setValidTo] = useState(tax?.valid_to ?? '');
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const editing = tax !== null;
  const perUnit = calculation === 'per_unit';

  const changeClass = (next: string) => {
    setTaxClass(next);
    setKind(KINDS[next]?.[0] ?? 'iva');
    setCalculation('percentage');
  };
  const changeKind = (next: string) => {
    setKind(next);
    if (next !== 'impoconsumo') setCalculation('percentage');
  };

  const validate = (): Errors => {
    const found: Errors = {};
    if (name.trim() === '') found.name = t('taxes.form.required');
    if (!/^\d+(\.\d{1,4})?$/.test(rate.trim())) {
      found.rate = t('taxes.form.rateInvalid');
    }
    if (sales.text.trim() !== '' && sales.id === null) {
      found.sales_account_id = t('taxes.form.pickAccount');
    }
    if (purchases.text.trim() !== '' && purchases.id === null) {
      found.purchase_account_id = t('taxes.form.pickAccount');
    }
    if (validFrom && validTo && validTo < validFrom) {
      found.valid_to = t('taxes.form.endBeforeStart');
    }
    return found;
  };

  const submit = async () => {
    const found = validate();
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) return;
    const payload: TaxPayload = {
      name: name.trim(),
      calculation,
      rate: rate.trim(),
      sales_account_id: sales.id,
      purchase_account_id: purchases.id,
      valid_from: validFrom || null,
      valid_to: validTo || null,
    };
    setBusy(true);
    try {
      await onSave(editing ? payload : {...payload, tax_class: taxClass, kind});
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
      title={editing ? t('taxes.form.editTitle') : t('taxes.form.newTitle')}
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={failure}
    >
      <Field
        label={t('taxes.form.name')}
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
        <p className="span-2 muted small">
          {t(`taxes.class.${taxClass}`)} · {t(`taxes.kind.${kind}`)}
        </p>
      ) : (
        <>
          <Field label={t('taxes.form.class')} error={errors.tax_class}>
            <select
              value={taxClass}
              onChange={(e) => changeClass(e.target.value)}
            >
              {Object.keys(KINDS).map((value) => (
                <option key={value} value={value}>
                  {t(`taxes.class.${value}`)}
                </option>
              ))}
            </select>
          </Field>
          <Field label={t('taxes.form.kind')} error={errors.kind}>
            <select value={kind} onChange={(e) => changeKind(e.target.value)}>
              {(KINDS[taxClass] ?? []).map((value) => (
                <option key={value} value={value}>
                  {t(`taxes.kind.${value}`)}
                </option>
              ))}
            </select>
          </Field>
        </>
      )}
      {kind === 'impoconsumo' && (
        <Field label={t('taxes.form.calculation')} error={errors.calculation}>
          <select
            value={calculation}
            onChange={(e) => setCalculation(e.target.value)}
          >
            <option value="percentage">
              {t('taxes.calculation.percentage')}
            </option>
            <option value="per_unit">{t('taxes.calculation.per_unit')}</option>
          </select>
        </Field>
      )}
      <Field
        label={perUnit ? t('taxes.form.valuePerUnit') : t('taxes.form.rate')}
        hint={perUnit ? t('taxes.form.valueHint') : t('taxes.form.rateHint')}
        error={errors.rate}
      >
        <MoneyInput places={4} value={rate} onChange={setRate} />
      </Field>
      <Field
        label={t('taxes.form.validFrom')}
        error={errors.valid_from}
        optional
      >
        <DateInput value={validFrom} onChange={setValidFrom} />
      </Field>
      <Field label={t('taxes.form.validTo')} error={errors.valid_to} optional>
        <DateInput value={validTo} onChange={setValidTo} />
      </Field>
      <Field
        label={t('taxes.form.salesAccount')}
        hint={t('taxes.form.accountHint')}
        error={errors.sales_account_id}
        optional
      >
        <AccountPicker value={sales} onChange={setSales} />
      </Field>
      <Field
        label={t('taxes.form.purchaseAccount')}
        hint={t('taxes.form.accountHint')}
        error={errors.purchase_account_id}
        optional
      >
        <AccountPicker value={purchases} onChange={setPurchases} />
      </Field>
    </FormModal>
  );
}
