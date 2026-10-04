import {useEffect, useId, useRef, useState, type KeyboardEvent} from 'react';
import {
  identificationLabel,
  searchTerceros,
  type TerceroSummary,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';

const MIN_CHARS = 3;
const DEBOUNCE_MS = 250;

type Answer = {
  term: string;
  status: 'ready' | 'failed';
  items: TerceroSummary[];
};

/**
 * The client of a receipt: a search box (from 3 characters, §4.6) with its matches below, the ARIA combobox pattern
 * (↓ ↑ walk, Enter chooses, Escape closes). Any tercero may owe an invoice, so every role is searched, inactive ones
 * too (a deactivated client may still pay what it owes).
 */
export function ClientPicker({
  value,
  onChange,
  disabled = false,
  ...aria
}: {
  'value': {id: string; name: string} | null;
  'onChange': (client: {id: string; name: string} | null) => void;
  'disabled'?: boolean;
  'id'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}) {
  const {t} = useTranslation();
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
      searchTerceros({q: term, per_page: 10})
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
  }, [term, open, searchable]);

  const answered = searchable && answer?.term === term ? answer : null;
  const items = answered?.items ?? [];
  const message = !searchable
    ? term === ''
      ? null
      : t('cashReceipt.form.search.minChars', {count: MIN_CHARS})
    : !answered
      ? t('cashReceipt.form.search.searching')
      : answered.status === 'failed'
        ? t('cashReceipt.form.search.failed')
        : items.length === 0
          ? t('cashReceipt.form.search.none')
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
    <div className="combo cash-receipt-client">
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
        placeholder={t('cashReceipt.form.clientPlaceholder')}
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
          className="combo-popup cash-receipt-client-popup"
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
