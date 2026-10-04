import {useEffect, useState} from 'react';
import {searchTerceros, type TerceroSummary} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {DateInput, Field} from '@/shared/ui';
import type {EditorErrors} from '@/widgets/document-editor';
import type {QuotationExtras} from '../model/draftMapping';

/** The employees to pick the responsable from: the active ones, plus the one the quotation already has. */
function useEmployees(current: QuotationExtras['responsible']) {
  const [employees, setEmployees] = useState<TerceroSummary[]>([]);
  useEffect(() => {
    let cancelled = false;
    searchTerceros({role: 'empleado', active: '1', per_page: 100})
      .then((page) => !cancelled && setEmployees(page.items))
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);
  const options = employees.map((e) => ({id: e.id, name: e.display_name}));
  if (current && !options.some((o) => o.id === current.id)) {
    options.unshift({id: current.id, name: current.name});
  }
  return options;
}

/** headerExtra of the document form: Responsable de la cotización and the offer's fecha de vencimiento (§4.7). */
export function QuotationHeaderFields({
  extras,
  errors,
  onChange,
}: {
  extras: QuotationExtras;
  errors: EditorErrors;
  onChange: (next: Partial<QuotationExtras>) => void;
}) {
  const {t} = useTranslation();
  const employees = useEmployees(extras.responsible);
  return (
    <>
      <Field
        label={t('quotation.fields.responsible')}
        error={errors['responsible_id']}
        optional
      >
        <select
          value={extras.responsible?.id ?? ''}
          onChange={(e) => {
            const found = employees.find((o) => o.id === e.target.value);
            onChange({responsible: found ?? null});
          }}
        >
          <option value="">{t('quotation.fields.noResponsible')}</option>
          {employees.map((employee) => (
            <option key={employee.id} value={employee.id}>
              {employee.name}
            </option>
          ))}
        </select>
      </Field>
      <Field label={t('quotation.fields.expiry')} error={errors['expiry_date']}>
        <DateInput
          value={extras.expiry_date}
          onChange={(iso) => onChange({expiry_date: iso, expiry_touched: true})}
        />
      </Field>
    </>
  );
}

/** footerExtra: Encabezado and Condiciones comerciales, plain text (what is typed is never read as markup). */
export function QuotationFooterFields({
  extras,
  errors,
  onChange,
}: {
  extras: QuotationExtras;
  errors: EditorErrors;
  onChange: (next: Partial<QuotationExtras>) => void;
}) {
  const {t} = useTranslation();
  return (
    <>
      <Field
        label={t('quotation.fields.header')}
        hint={t('quotation.fields.headerHint')}
        error={errors['header']}
        optional
      >
        <textarea
          rows={4}
          maxLength={5000}
          value={extras.header}
          onChange={(e) => onChange({header: e.target.value})}
        />
      </Field>
      <Field
        label={t('quotation.fields.terms')}
        hint={t('quotation.fields.termsHint')}
        error={errors['terms']}
        optional
      >
        <textarea
          rows={4}
          maxLength={5000}
          value={extras.terms}
          onChange={(e) => onChange({terms: e.target.value})}
        />
      </Field>
    </>
  );
}
