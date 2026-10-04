import {useEffect, useId, useRef, useState, type KeyboardEvent} from 'react';
import {
  searchTerceros,
  type Role,
  type TerceroSummary,
} from '../api/terceroApi';
import {identificationLabel} from '../lib/label';
import './terceroPicker.css';

const MIN_CHARS = 3;
const DEBOUNCE_MS = 250;

type Answer = {
  term: string;
  status: 'ready' | 'failed';
  items: TerceroSummary[];
};

export interface TerceroPickerLabels {
  placeholder: string;
  /** "Escribe al menos 3 caracteres." */
  minChars: (count: number) => string;
  searching: string;
  none: string;
  failed: string;
}

export interface PickedTercero {
  id: string;
  name: string;
}

/**
 * A tercero search box (from 3 characters, §4.6) with its matches below, the ARIA combobox pattern (↓ ↑ walk, Enter
 * chooses, Escape closes). Generic: the page gives the words (`labels`) and, if it wants one, the role to search
 * (`role="proveedor"` for a recibo de pago); without a role every tercero is searched. Inactive ones are included: a
 * deactivated tercero may still owe or be owed what it already has.
 */
export function TerceroPicker({
  value,
  onChange,
  labels,
  role = '',
  disabled = false,
  ...aria
}: {
  'value': PickedTercero | null;
  'onChange': (tercero: PickedTercero | null) => void;
  'labels': TerceroPickerLabels;
  'role'?: Role | '';
  'disabled'?: boolean;
  'id'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}) {
  const listId = `${useId()}-list`;
  const [text, setText] = useState(value?.name ?? '');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [answer, setAnswer] = useState<Answer | null>(null);

  const term = text.trim();
  const searchable = term.length >= MIN_CHARS;
  const latest = useRef(term);
  useEffect(() => {
    latest.current = term;
  });
  useEffect(() => {
    if (!open || !searchable) return;
    let cancelled = false;
    const timer = setTimeout(() => {
      searchTerceros({q: term, role, per_page: 10})
        .then((page) => {
          if (cancelled) return;
          setAnswer({term, status: 'ready', items: page.items});
          setActive(page.items.length > 0 ? 0 : -1);
        })
        .catch(() => {
          if (!cancelled) setAnswer({term, status: 'failed', items: []});
        });
    }, DEBOUNCE_MS);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [term, role, open, searchable]);

  const answered = searchable && answer?.term === term ? answer : null;
  const items = answered?.items ?? [];
  const message = !searchable
    ? term === ''
      ? null
      : labels.minChars(MIN_CHARS)
    : !answered
      ? labels.searching
      : answered.status === 'failed'
        ? labels.failed
        : items.length === 0
          ? labels.none
          : null;
  const expanded = open && (message !== null || items.length > 0);
  const optionId = (i: number) => `${listId}-${i}`;

  const choose = (tercero: TerceroSummary) => {
    setText(tercero.display_name);
    setOpen(false);
    onChange({id: tercero.id, name: tercero.display_name});
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (!open) setOpen(true);
      else if (items.length > 0) setActive((i) => (i + 1) % items.length);
    } else if (event.key === 'ArrowUp' && open && items.length > 0) {
      event.preventDefault();
      setActive((i) => (i <= 0 ? items.length - 1 : i - 1));
    } else if (event.key === 'Enter' && open && items[active]) {
      event.preventDefault();
      choose(items[active]);
    } else if (event.key === 'Escape' && open) {
      event.preventDefault();
      setOpen(false);
    }
  };

  return (
    <div className="combo tercero-picker">
      <input
        {...aria}
        type="text"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={expanded}
        aria-controls={listId}
        aria-activedescendant={
          expanded && items[active] ? optionId(active) : undefined
        }
        autoComplete="off"
        placeholder={labels.placeholder}
        value={text}
        disabled={disabled}
        onChange={(event) => {
          setText(event.target.value);
          setOpen(true);
          if (value) onChange(null);
        }}
        onKeyDown={onKeyDown}
        onBlur={() => setOpen(false)}
      />
      {expanded && (
        <div
          className="combo-popup tercero-picker-popup"
          // Choosing with the mouse must not blur the box first (that would close the list).
          onMouseDown={(event) => event.preventDefault()}
        >
          {message && (
            <p className="combo-status small muted" role="status">
              {message}
            </p>
          )}
          {items.length > 0 && (
            <ul id={listId} role="listbox" className="combo-list">
              {items.map((tercero, i) => (
                <li
                  key={tercero.id}
                  id={optionId(i)}
                  role="option"
                  aria-selected={i === active}
                  className={i === active ? 'is-active' : undefined}
                  onClick={() => choose(tercero)}
                >
                  <span className="combo-label">{tercero.display_name}</span>{' '}
                  <span className="combo-detail small muted">
                    {identificationLabel(tercero)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
