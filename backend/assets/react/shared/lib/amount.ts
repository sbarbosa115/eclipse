// Numbers as a Colombian types them (§5 NFR): "1.190.000,50", "595000,5", "595000.50". Read into the decimal string
// the API takes (a point, no thousands separator) and shown back the same way. Strings only, never floats (CLAUDE.md).

const PLAIN = /^\d+$/;

/**
 * What is typed ("1.190.000,50", "595000,5", "595000.50", "$ 1.190.000") → the decimal with a point, with the places
 * that were typed ("1190000.50", "595000.5"); null when it is not a number with at most `places` decimals. A dot
 * followed by groups of three digits is a thousands separator (never after a lone 0: "0.966" is a rate), a comma is the decimal separator; a lone dot with
 * fewer than three decimals (or up to `places` when they are not three) is a decimal point.
 */
export function parseDecimal(text: string, places: number): string | null {
  const plain = text.replace(/[\s$]/g, '');
  if (plain === '') return null;
  const fraction = `\\d{1,${places}}`;
  const thousands = new RegExp(`^[1-9]\\d{0,2}(\\.\\d{3})+(,${fraction})?$`);
  const comma = new RegExp(`^\\d+(,${fraction})?$`);
  const point = new RegExp(`^\\d+(\\.${fraction})?$`);
  let normalized: string;
  if (thousands.test(plain)) {
    normalized = plain.replace(/\./g, '').replace(',', '.');
  } else if (comma.test(plain)) {
    normalized = plain.replace(',', '.');
  } else if (point.test(plain)) {
    normalized = plain;
  } else {
    return null;
  }
  const [whole = '0', decimals] = normalized.split('.');
  const digits = whole.replace(/^0+(?=\d)/, '');
  return decimals === undefined ? digits : `${digits}.${decimals}`;
}

/** Pesos as typed → "1190000.50" (always two places); null when it is not an amount. */
export function parseAmount(text: string): string | null {
  const value = parseDecimal(text, 2);
  if (value === null) return null;
  const [whole = '0', decimals = ''] = value.split('.');
  return `${whole}.${decimals.padEnd(2, '0')}`;
}

/**
 * A decimal string ("1190000.50", "49999.9000") as it would be typed: "1.190.000,50", "49.999,90". Trailing zero
 * decimals are dropped ("19.0000" → "19"), and a fraction keeps at least two places. Anything else is returned as is.
 */
export function formatDecimalInput(decimal: string): string {
  const [whole = '', decimals = '', ...rest] = decimal.split('.');
  if (!PLAIN.test(whole) || rest.length > 0) return decimal;
  if (decimals !== '' && !PLAIN.test(decimals)) return decimal;
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const trimmed = decimals.replace(/0+$/, '');
  return trimmed === '' ? grouped : `${grouped},${trimmed.padEnd(2, '0')}`;
}
