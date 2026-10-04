// Money, numbers and dates as a Colombian reads them (§5 NFR): COP with thousands separators, DD/MM/YYYY.
const COP = new Intl.NumberFormat('es-CO', {
  style: 'currency',
  currency: 'COP',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

/** "1190000.00" (the API's decimal string) → "$ 1.190.000,00". Never parses through arithmetic: display only. */
export function formatMoney(amount: string | null | undefined): string {
  if (amount == null || amount === '') return '';
  return COP.format(Number(amount)).replace(/\u00a0/g, ' ');
}

const BOGOTA_DAY = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'America/Bogota',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});

/**
 * "2026-10-03" → "03/10/2026". A moment ("2026-10-04T02:00:00+00:00", when something was emitted or voided) is shown
 * on Colombia's calendar: 9 p.m. in Bogotá is still the 3rd, whatever UTC says.
 */
export function formatDate(isoDate: string | null | undefined): string {
  if (!isoDate) return '';
  const day =
    isoDate.length > 10 ? BOGOTA_DAY.format(new Date(isoDate)) : isoDate;
  const [y, m, d] = day.slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

/** A NIT with its DV: "900123456-8". */
export function formatNit(nit: string, checkDigit?: string | null): string {
  return checkDigit ? `${nit}-${checkDigit}` : nit;
}
