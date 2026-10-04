import {SearchCombobox, type ComboOption} from '@/shared/ui';
import {
  searchTerceros,
  type Role,
  type TerceroSummary,
} from '../api/terceroApi';
import {identificationLabel} from '../lib/label';

const MIN_CHARS = 3;

export interface TerceroPickerLabels {
  placeholder: string;
  /** "Escribe al menos 3 caracteres." */
  minChars: (count: number) => string;
  searching: string;
  none: string;
  failed: string;
  /** "+ Crear nuevo", when the picker offers to create one (`onCreate`). */
  create?: string;
}

export interface PickedTercero {
  id: string;
  name: string;
}

/**
 * The one tercero search (from 3 characters, §4.6), on the kit's SearchCombobox: ↓ ↑ walk, Enter chooses, Escape
 * closes. The page gives the words (`labels`) and, if it wants them, the role to search (`role="proveedor"` for a
 * recibo de pago), `activeOnly` (a new document names active terceros) and `onCreate` (the document editor's
 * "+ Crear nuevo"). Without `activeOnly` inactive ones are included: a deactivated tercero may still owe or be owed
 * what it already has. A tercero chosen from outside (one just created) shows its name.
 */
export function TerceroPicker({
  value,
  onChange,
  labels,
  role = '',
  activeOnly = false,
  onCreate,
  disabled = false,
  ...aria
}: {
  'value': PickedTercero | null;
  'onChange': (tercero: PickedTercero | null) => void;
  'labels': TerceroPickerLabels;
  'role'?: Role | '';
  'activeOnly'?: boolean;
  'onCreate'?: (text: string) => void;
  'disabled'?: boolean;
  'id'?: string;
  'aria-label'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}) {
  const search = async (
    term: string,
  ): Promise<ComboOption<TerceroSummary>[]> => {
    const page = await searchTerceros({
      q: term,
      role,
      active: activeOnly ? '1' : '',
      per_page: 10,
    });
    return page.items.map((tercero) => ({
      id: tercero.id,
      label: tercero.display_name,
      detail: identificationLabel(tercero),
      value: tercero,
    }));
  };

  return (
    <SearchCombobox<TerceroSummary>
      {...aria}
      selectedLabel={value?.name ?? ''}
      minChars={MIN_CHARS}
      search={search}
      placeholder={labels.placeholder}
      messages={labels}
      disabled={disabled}
      onSelect={(option) =>
        onChange({id: option.id, name: option.value.display_name})
      }
      onClear={() => onChange(null)}
      onCreate={onCreate}
    />
  );
}
