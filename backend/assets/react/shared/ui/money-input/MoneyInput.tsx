import {useState, type InputHTMLAttributes} from 'react';
import {formatDecimalInput, parseDecimal} from '../../lib/amount';

type Props = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  'value' | 'onChange' | 'type'
> & {
  /** A decimal string with a point ("1190000.50"), or '' for none. */
  value: string;
  /**
   * The decimal with a point when what is typed is a number ("1.190.000,50" → "1190000.50"); '' when it is emptied;
   * otherwise the text as typed, which no decimal check accepts, so the form's own validation says it is invalid.
   */
  onChange: (value: string) => void;
  /** At most this many decimals: 2 for money (the default), 4 for unit prices and rates. */
  places?: 2 | 4;
};

/** What the parent is handed for a text. */
function valueOf(text: string, places: number): string {
  if (text.trim() === '') return '';
  return parseDecimal(text, places) ?? text;
}

/**
 * Money (or any decimal) as a Colombian types and reads it: "1.190.000,50", "595000,5" or "595000.50" are all
 * accepted, and the parent gets a decimal string with a point, never a float (CLAUDE.md). Use it inside a Field like
 * any input.
 */
export function MoneyInput({
  value,
  onChange,
  onBlur,
  places = 2,
  ...rest
}: Props) {
  const [text, setText] = useState(formatDecimalInput(value));
  // Follow a value set from outside (adjusted while rendering, not in an effect).
  const [shown, setShown] = useState(value);
  if (shown !== value) {
    setShown(value);
    if (valueOf(text, places) !== value) setText(formatDecimalInput(value));
  }

  return (
    <input
      {...rest}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      value={text}
      onChange={(event) => {
        setText(event.target.value);
        const next = valueOf(event.target.value, places);
        setShown(next);
        onChange(next);
      }}
      onBlur={(event) => {
        const parsed = parseDecimal(text, places);
        if (parsed !== null) setText(formatDecimalInput(parsed));
        onBlur?.(event);
      }}
    />
  );
}
