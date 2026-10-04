import {useTranslation} from '@/shared/i18n';
import {Field} from '@/shared/ui';
import {checkDigitOf} from '../lib/checkDigit';
import {
  IDENTIFICATION_TYPES,
  PERSON_TYPES,
  isCompany,
  type FormErrors,
} from '../model/form';

export interface IdentityValues {
  person_type: string;
  identification_type: string;
  identification_number: string;
  check_digit: string;
  first_names: string;
  last_names: string;
  business_name: string;
}

/**
 * Who the tercero is: tipo, identification with its DV (computed for a NIT, editable) and the name of a person or the
 * razón social of a company. The full form and the quick-create form share it. Renders grid cells.
 */
export function IdentificationFields({
  value,
  errors,
  onChange,
  disabled = false,
}: {
  value: IdentityValues;
  errors: FormErrors;
  onChange: (patch: Partial<IdentityValues>) => void;
  disabled?: boolean;
}) {
  const {t} = useTranslation();
  const nit = value.identification_type === 'nit';
  const computed = nit ? checkDigitOf(value.identification_number) : null;
  return (
    <>
      <Field label={t('terceros.form.fields.personType')}>
        <select
          value={value.person_type}
          disabled={disabled}
          onChange={(e) => onChange({person_type: e.target.value})}
        >
          {PERSON_TYPES.map((type) => (
            <option key={type} value={type}>
              {t(`terceros.personTypes.${type}`)}
            </option>
          ))}
        </select>
      </Field>
      <Field label={t('terceros.form.fields.identificationType')}>
        <select
          value={value.identification_type}
          disabled={disabled}
          onChange={(e) => onChange({identification_type: e.target.value})}
        >
          {IDENTIFICATION_TYPES.map((type) => (
            <option key={type} value={type}>
              {t(`terceros.identificationTypes.${type}`)}
            </option>
          ))}
        </select>
      </Field>
      <Field
        label={t('terceros.form.fields.identificationNumber')}
        error={errors['identification_number']}
      >
        <input
          value={value.identification_number}
          disabled={disabled}
          inputMode={nit ? 'numeric' : undefined}
          maxLength={30}
          onChange={(e) => onChange({identification_number: e.target.value})}
        />
      </Field>
      {nit && (
        <Field
          label={t('terceros.form.fields.checkDigit')}
          error={errors['check_digit']}
          hint={
            computed
              ? t('terceros.form.fields.checkDigitHint', {dv: computed})
              : undefined
          }
        >
          <input
            value={value.check_digit}
            placeholder={computed ?? ''}
            disabled={disabled}
            inputMode="numeric"
            maxLength={1}
            onChange={(e) => onChange({check_digit: e.target.value})}
          />
        </Field>
      )}
      {isCompany(value) ? (
        <Field
          label={t('terceros.form.fields.businessName')}
          error={errors['business_name']}
          className="span-2"
        >
          <input
            value={value.business_name}
            disabled={disabled}
            maxLength={200}
            onChange={(e) => onChange({business_name: e.target.value})}
          />
        </Field>
      ) : (
        <>
          <Field
            label={t('terceros.form.fields.firstNames')}
            error={errors['first_names']}
          >
            <input
              value={value.first_names}
              disabled={disabled}
              maxLength={120}
              onChange={(e) => onChange({first_names: e.target.value})}
            />
          </Field>
          <Field
            label={t('terceros.form.fields.lastNames')}
            error={errors['last_names']}
            optional
          >
            <input
              value={value.last_names}
              disabled={disabled}
              maxLength={120}
              onChange={(e) => onChange({last_names: e.target.value})}
            />
          </Field>
        </>
      )}
    </>
  );
}
