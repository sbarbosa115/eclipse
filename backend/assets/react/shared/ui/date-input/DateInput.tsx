import {useState, type InputHTMLAttributes} from 'react';

type Props = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  'value' | 'onChange' | 'type'
> & {
  /** ISO (YYYY-MM-DD), or '' for none. */
  value: string;
  /** ISO when what is typed is a real date, '' otherwise. */
  onChange: (iso: string) => void;
};

const DMY = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;

/** "3/10/2026" → "2026-10-03"; null when it is not a real date. */
export function parseDayFirst(text: string): string | null {
  const match = DMY.exec(text.trim());
  if (!match) return null;
  const [, d, m, y] = match.map(Number) as [number, number, number, number];
  const date = new Date(Date.UTC(y, m - 1, d));
  if (
    date.getUTCFullYear() !== y ||
    date.getUTCMonth() !== m - 1 ||
    date.getUTCDate() !== d
  ) {
    return null;
  }
  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function show(iso: string): string {
  if (!/^\d{4}-\d{2}-\d{2}/.test(iso)) return '';
  const [y, m, d] = iso.slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

/**
 * A date as a Colombian reads and writes it, DD/MM/YYYY, whatever the browser's language (a native date input shows
 * the browser's own format: §5 asks for DD/MM/YYYY). Use it inside a Field like any input.
 */
export function DateInput({value, onChange, onBlur, ...rest}: Props) {
  const [text, setText] = useState(show(value));
  // Follow a value set from outside (adjusted while rendering, not in an effect).
  const [shown, setShown] = useState(value);
  if (shown !== value) {
    setShown(value);
    if (parseDayFirst(text) !== value) setText(show(value));
  }

  return (
    <input
      {...rest}
      type="text"
      inputMode="numeric"
      placeholder={rest.placeholder ?? 'DD/MM/AAAA'}
      value={text}
      onChange={(event) => {
        setText(event.target.value);
        const iso = parseDayFirst(event.target.value) ?? '';
        setShown(iso);
        onChange(iso);
      }}
      onBlur={(event) => {
        const iso = parseDayFirst(text);
        if (iso) setText(show(iso));
        onBlur?.(event);
      }}
    />
  );
}
