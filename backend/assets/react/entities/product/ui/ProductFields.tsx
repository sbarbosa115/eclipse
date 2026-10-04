import {useTranslation} from '@/shared/i18n';
import {Field} from '@/shared/ui';
import type {Tax} from '../api/productApi';

/** A select of the company's taxes of one class, with its own first option ("the company's", "none"). */
export function TaxSelect({
  label,
  taxes,
  value,
  emptyLabel,
  error,
  onChange,
}: {
  label: string;
  taxes: Tax[];
  value: string;
  emptyLabel: string;
  error?: string | null;
  onChange: (id: string) => void;
}) {
  return (
    <Field label={label} error={error}>
      <select value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">{emptyLabel}</option>
        {taxes.map((tax) => (
          <option key={tax.id} value={tax.id}>
            {tax.name}
          </option>
        ))}
      </select>
    </Field>
  );
}

/** Tipo: producto or servicio. */
export function TypeSelect({
  value,
  onChange,
}: {
  value: string;
  onChange: (type: 'producto' | 'servicio') => void;
}) {
  const {t} = useTranslation();
  return (
    <Field label={t('catalog.type.label')}>
      <select
        value={value}
        onChange={(e) =>
          onChange(e.target.value === 'servicio' ? 'servicio' : 'producto')
        }
      >
        <option value="producto">{t('catalog.type.producto')}</option>
        <option value="servicio">{t('catalog.type.servicio')}</option>
      </select>
    </Field>
  );
}

/** "Incluir IVA en el precio": the price already carries the charge tax. */
export function IncludesTaxCheckbox({
  checked,
  onChange,
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  const {t} = useTranslation();
  return (
    <div className="field span-2">
      <label className="checkbox">
        <input
          type="checkbox"
          checked={checked}
          onChange={(e) => onChange(e.target.checked)}
        />
        <span>{t('catalog.form.includesTax')}</span>
      </label>
      <span className="admin-field-hint">
        {t('catalog.form.includesTaxHint')}
      </span>
    </div>
  );
}
