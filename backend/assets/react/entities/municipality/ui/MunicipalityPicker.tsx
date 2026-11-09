import {municipalityLabel} from '@/shared/lib';
import {SearchCombobox, type ComboOption} from '@/shared/ui';
import {searchMunicipalities, type Municipality} from '../api/municipalityApi';

const MIN_CHARS = 2;

export interface MunicipalityPickerLabels {
  placeholder: string;
  /** "Escribe al menos 2 caracteres." */
  minChars: (count: number) => string;
  searching: string;
  none: string;
  failed: string;
}

export interface PickedMunicipality {
  code: string;
  /** "Antioquia › Medellín (05001)" */
  label: string;
}

/**
 * The municipality search on DIVIPOLA (from 2 characters, by name or by code), on the kit's SearchCombobox. It shows
 * "Antioquia › Medellín (05001)". A municipality chosen from outside (a saved tercero) shows its label.
 */
export function MunicipalityPicker({
  value,
  onChange,
  labels,
  disabled = false,
  ...aria
}: {
  'value': PickedMunicipality | null;
  'onChange': (municipality: PickedMunicipality | null) => void;
  'labels': MunicipalityPickerLabels;
  'disabled'?: boolean;
  'id'?: string;
  'aria-label'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}) {
  const search = async (term: string): Promise<ComboOption<Municipality>[]> =>
    (await searchMunicipalities(term)).map((m) => ({
      id: m.code,
      label: municipalityLabel(m),
      value: m,
    }));

  return (
    <SearchCombobox<Municipality>
      {...aria}
      selectedLabel={value?.label ?? ''}
      minChars={MIN_CHARS}
      search={search}
      placeholder={labels.placeholder}
      messages={labels}
      disabled={disabled}
      onSelect={(option) =>
        onChange({code: option.value.code, label: option.label})
      }
      onClear={() => onChange(null)}
    />
  );
}
