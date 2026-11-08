import {apiGet, apiPut, type Schema} from '@/shared/api';

export type ExogenaConcept = Schema<'ExogenaConceptOutput'>;

export const EXOGENA_FORMATS = ['1001', '1007'] as const;

export function fetchConcepts() {
  return apiGet<{items: ExogenaConcept[]}>('/ledger/exogena-concepts');
}

/** The whole table: the server replaces what it had with it. */
export function replaceConcepts(items: ExogenaConcept[]) {
  return apiPut<{items: ExogenaConcept[]}>('/ledger/exogena-concepts', {
    items,
  });
}
