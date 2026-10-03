// When a person last signed in, as Colombia reads it: the API's instants are UTC, the office is in Bogotá.
const FORMAT = new Intl.DateTimeFormat('es-CO', {
  timeZone: 'America/Bogota',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
});

/** "2026-10-03T15:30:00+00:00" → "03/10/2026 10:30". */
export function formatDateTime(instant: string): string {
  const parts = Object.fromEntries(
    FORMAT.formatToParts(new Date(instant)).map((p) => [p.type, p.value]),
  );
  return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}`;
}

/** "2026-10-10T15:30:00+00:00" → "10/10/2026" (Bogotá). */
export function formatDay(instant: string): string {
  return formatDateTime(instant).slice(0, 10);
}
