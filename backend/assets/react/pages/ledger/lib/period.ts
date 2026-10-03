/** Today as YYYY-MM-DD, in the browser's time zone. */
export function today(): string {
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** The first day of this year: reports default to the year so far. */
export function startOfYear(): string {
  return `${today().slice(0, 4)}-01-01`;
}
