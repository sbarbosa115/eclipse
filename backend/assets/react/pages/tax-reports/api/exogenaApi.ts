import {apiGet, type Schema} from '@/shared/api';

// Observations 2026-11, item 9 exogena: the exógena working papers of a taxable year.

export type ExogenaPaper = Schema<'ExogenaOutput'>;
export type ExogenaRow = Schema<'ExogenaRowOutput'>;

export const EXOGENA_FORMATS = [
  '1001',
  '1003',
  '1005',
  '1006',
  '1007',
  '1008',
  '1009',
] as const;
export type ExogenaFormat = (typeof EXOGENA_FORMATS)[number];

/** Formats reported per account under a DIAN concept (the concept table). */
export const BY_CONCEPT: ReadonlySet<string> = new Set(['1001', '1007']);

export const exogenaPath = (format: ExogenaFormat) =>
  `/reports/taxes/exogena/${format}`;

export interface ExogenaFilters {
  year: string;
  /** Pesos as a decimal string, or '' for none (everyone by name). */
  threshold: string;
}

/** The query both the paper and its files take: the year, and the threshold when there is one. */
export function exogenaParams({year, threshold}: ExogenaFilters) {
  return {year, threshold: threshold === '' ? undefined : threshold};
}

export function fetchExogena(format: ExogenaFormat, filters: ExogenaFilters) {
  const search = new URLSearchParams();
  for (const [name, value] of Object.entries(exogenaParams(filters))) {
    if (value) search.set(name, value);
  }
  return apiGet<ExogenaPaper>(`${exogenaPath(format)}?${search.toString()}`);
}
