import {Field} from '@/shared/ui';
import {COUNTRY_CODES, countryName} from '../lib/countries';
import {
  MunicipalityPicker,
  type MunicipalityPickerLabels,
  type PickedMunicipality,
} from './MunicipalityPicker';

/** What the form keeps of a place: the API's keys, plus the label of the chosen municipality. */
export interface PlaceValues {
  country_code: string;
  municipality_code: string;
  municipality_label: string;
  city: string;
}

export interface PlaceLabels extends MunicipalityPickerLabels {
  country: string;
  municipality: string;
  city: string;
}

/** The place of a Colombian tercero or company is its municipality; abroad it is a free-text city. */
export const isColombia = (place: {country_code: string}) =>
  place.country_code === 'CO';

/**
 * País, then Municipio (Colombia: DIVIPOLA picker, required) or Ciudad (any other country: free text, required).
 * Renders grid cells for a `form-grid`. `errors` are keyed `municipality_code` and `city`.
 */
export function PlaceFields({
  value,
  errors,
  onChange,
  labels,
  disabled = false,
}: {
  value: PlaceValues;
  errors: Record<string, string>;
  onChange: (patch: Partial<PlaceValues>) => void;
  labels: PlaceLabels;
  disabled?: boolean;
}) {
  const picked: PickedMunicipality | null =
    value.municipality_code === ''
      ? null
      : {code: value.municipality_code, label: value.municipality_label};
  // A country the saved record has but the list lacks is still shown.
  const codes: readonly string[] = COUNTRY_CODES.includes(
    value.country_code as (typeof COUNTRY_CODES)[number],
  )
    ? COUNTRY_CODES
    : [...COUNTRY_CODES, value.country_code];
  return (
    <>
      <Field label={labels.country} error={errors['country_code']}>
        <select
          value={value.country_code}
          disabled={disabled}
          onChange={(e) => onChange({country_code: e.target.value})}
        >
          {codes.map((code) => (
            <option key={code} value={code}>
              {countryName(code)}
            </option>
          ))}
        </select>
      </Field>
      {isColombia(value) ? (
        <Field label={labels.municipality} error={errors['municipality_code']}>
          <MunicipalityPicker
            value={picked}
            labels={labels}
            disabled={disabled}
            onChange={(m) =>
              onChange({
                municipality_code: m?.code ?? '',
                municipality_label: m?.label ?? '',
              })
            }
          />
        </Field>
      ) : (
        <Field label={labels.city} error={errors['city']}>
          <input
            value={value.city}
            maxLength={100}
            disabled={disabled}
            onChange={(e) => onChange({city: e.target.value})}
          />
        </Field>
      )}
    </>
  );
}
