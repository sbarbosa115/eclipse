// The calendar the server keeps (America/Bogota): "today" in a document's default date, a report's period or a
// due date must be Colombia's, not the browser's, or a browser in another time zone (or UTC after 7 p.m.) offers a
// date the server refuses as future.
const BOGOTA = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'America/Bogota',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});

/** Today in Colombia, as the API writes dates (YYYY-MM-DD). */
export function todayInColombia(now: Date = new Date()): string {
  return BOGOTA.format(now);
}

/** An ISO date moved by whole days (calendar arithmetic, no time zone involved). */
export function addDays(isoDate: string, days: number): string {
  const [y, m, d] = isoDate.split('-').map(Number);
  const date = new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}
