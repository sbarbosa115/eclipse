import {
  useEffect,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent,
} from 'react';
import {useTranslation} from '@/shared/i18n';

export interface ComboOption<T> {
  id: string;
  label: string;
  detail?: string;
  value: T;
}

type Status = 'idle' | 'short' | 'loading' | 'ready' | 'failed';

interface Props<T> {
  /** The label of what is chosen; '' while nothing is. */
  'selectedLabel': string;
  /** Characters typed before it searches (§4.6: 3 for a tercero). */
  'minChars': number;
  'search': (term: string) => Promise<ComboOption<T>[]>;
  'onSelect': (option: ComboOption<T>) => void;
  /** Typing over a choice drops it. */
  'onClear': () => void;
  /** "+ Crear nuevo", with what was typed. */
  'onCreate'?: (text: string) => void;
  'placeholder'?: string;
  'disabled'?: boolean;
  'id'?: string;
  'aria-label'?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
}

const DEBOUNCE_MS = 250;

/**
 * A search box with a list of matches (the ARIA combobox pattern): ↓ and ↑ walk the list, Enter chooses, Escape
 * closes. The last option is "+ Crear nuevo" when the form can create what is missing. The list floats over the page
 * (position: fixed), so a table that scrolls sideways does not cut it.
 */
export function SearchCombobox<T>({
  selectedLabel,
  minChars,
  search,
  onSelect,
  onClear,
  onCreate,
  placeholder,
  disabled = false,
  ...aria
}: Props<T>) {
  const {t} = useTranslation();
  const listId = `${useId()}-list`;
  const input = useRef<HTMLInputElement>(null);
  const [text, setText] = useState(selectedLabel);
  const [shown, setShown] = useState(selectedLabel);
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState<Status>('idle');
  const [options, setOptions] = useState<ComboOption<T>[]>([]);
  const [active, setActive] = useState(-1);
  const [position, setPosition] = useState<CSSProperties>({});
  const latest = useRef(search);
  useEffect(() => {
    latest.current = search;
  });

  // A choice made (here or by the form, e.g. a product created in a modal) shows its label. Adjusted while rendering.
  if (selectedLabel !== shown) {
    setShown(selectedLabel);
    if (selectedLabel !== '') {
      setText(selectedLabel);
      setOpen(false);
    }
  }

  const term = text.trim();
  useEffect(() => {
    if (!open) return;
    if (term.length < minChars) {
      setStatus(term === '' ? 'idle' : 'short');
      setOptions([]);
      return;
    }
    let cancelled = false;
    setStatus('loading');
    const timer = setTimeout(() => {
      latest
        .current(term)
        .then((found) => {
          if (cancelled) return;
          setOptions(found);
          setStatus('ready');
          setActive(found.length > 0 ? 0 : -1);
        })
        .catch(() => {
          if (!cancelled) setStatus('failed');
        });
    }, DEBOUNCE_MS);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [term, open, minChars]);

  const canCreate =
    onCreate !== undefined && (status === 'ready' || status === 'failed');
  const count = options.length + (canCreate ? 1 : 0);

  useLayoutEffect(() => {
    if (!open) return;
    const place = () => {
      const box = input.current?.getBoundingClientRect();
      if (!box) return;
      setPosition({
        top: box.bottom + 4,
        left: box.left,
        minWidth: Math.max(box.width, 260),
      });
    };
    place();
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    return () => {
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place);
    };
  }, [open]);

  const choose = (index: number) => {
    const option = options[index];
    if (option) {
      setText(option.label);
      setOpen(false);
      onSelect(option);
    } else if (canCreate && index === options.length) {
      setOpen(false);
      onCreate?.(term);
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.altKey) return;
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        if (!open) setOpen(true);
        else if (count > 0) setActive((i) => (i + 1) % count);
        break;
      case 'ArrowUp':
        if (!open) return;
        event.preventDefault();
        if (count > 0) setActive((i) => (i <= 0 ? count - 1 : i - 1));
        break;
      case 'Enter':
        if (open && active >= 0 && active < count) {
          event.preventDefault();
          choose(active);
        }
        break;
      case 'Escape':
        if (open) {
          event.preventDefault();
          setOpen(false);
        }
        break;
    }
  };

  const message =
    status === 'short'
      ? t('documentEditor.search.minChars', {count: minChars})
      : status === 'loading'
        ? t('documentEditor.search.searching')
        : status === 'failed'
          ? t('documentEditor.search.failed')
          : status === 'ready' && options.length === 0
            ? t('documentEditor.search.none')
            : null;
  const expanded = open && (message !== null || count > 0);
  const optionId = (i: number) => `${listId}-${i}`;

  return (
    <div className="combo">
      <input
        {...aria}
        ref={input}
        type="text"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={expanded}
        aria-controls={listId}
        aria-activedescendant={
          expanded && active >= 0 && active < count
            ? optionId(active)
            : undefined
        }
        autoComplete="off"
        value={text}
        placeholder={placeholder}
        disabled={disabled}
        onChange={(event) => {
          setText(event.target.value);
          setOpen(true);
          if (selectedLabel !== '') onClear();
        }}
        onKeyDown={onKeyDown}
        onBlur={() => setOpen(false)}
      />
      {expanded && (
        <div
          className="combo-popup"
          style={position}
          // Choosing with the mouse must not blur the box first (that would close the list).
          onMouseDown={(event) => event.preventDefault()}
        >
          {message && (
            <p className="combo-status small muted" role="status">
              {message}
            </p>
          )}
          {count > 0 && (
          <ul id={listId} role="listbox" className="combo-list">
            {options.map((option, i) => (
              <li
                key={option.id}
                id={optionId(i)}
                role="option"
                aria-selected={i === active}
                className={i === active ? 'is-active' : undefined}
                onClick={() => choose(i)}
              >
                <span className="combo-label">{option.label}</span>
                {option.detail && (
                  <span className="combo-detail small muted">
                    {' '}
                    {option.detail}
                  </span>
                )}
              </li>
            ))}
            {canCreate && (
              <li
                id={optionId(options.length)}
                role="option"
                aria-selected={active === options.length}
                className={`combo-create${active === options.length ? ' is-active' : ''}`}
                onClick={() => choose(options.length)}
              >
                {t('documentEditor.search.create')}
              </li>
            )}
          </ul>
          )}
        </div>
      )}
    </div>
  );
}
