import {todayInColombia} from '@/shared/lib';

/** Today as YYYY-MM-DD in Colombia's calendar (the server's). */
export function today(): string {
  return todayInColombia();
}

/** The first day of this year: reports default to the year so far. */
export function startOfYear(): string {
  return `${today().slice(0, 4)}-01-01`;
}
