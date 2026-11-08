import type {ExogenaConcept} from '../api/exogenaConceptsApi';

/** Rows as the server keeps them: by format, then prefix as text ("41" before "4135" before "42"). */
export function sorted(rows: ExogenaConcept[]): ExogenaConcept[] {
  return [...rows].sort((a, b) =>
    a.format === b.format
      ? a.account_prefix < b.account_prefix
        ? -1
        : a.account_prefix > b.account_prefix
          ? 1
          : 0
      : a.format < b.format
        ? -1
        : 1,
  );
}

export const sameRow = (a: ExogenaConcept, b: ExogenaConcept) =>
  a.format === b.format && a.account_prefix === b.account_prefix;
