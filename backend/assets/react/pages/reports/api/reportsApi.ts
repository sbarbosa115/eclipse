import {apiGet, type Schema} from '@/shared/api';

export type CarteraSide = 'clients' | 'suppliers';
export type Cartera = Schema<'CarteraOutput'>;
export type CarteraRow = Schema<'CarteraRowOutput'>;
export type CarteraDocuments = Schema<'CarteraDocumentsOutput'>;
export type CarteraDocument = Schema<'CarteraDocumentOutput'>;

const query = (params: Record<string, string | number | undefined>) => {
  const search = new URLSearchParams();
  for (const [name, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') search.set(name, String(value));
  }
  const text = search.toString();
  return text === '' ? '' : `?${text}`;
};

export interface CarteraQuery {
  as_of?: string;
  q?: string;
  page?: number;
  per_page?: number;
}

export function fetchCartera(side: CarteraSide, params: CarteraQuery) {
  return apiGet<Cartera>(`/reports/cartera/${side}${query({...params})}`);
}

export function fetchCarteraDocuments(
  side: CarteraSide,
  terceroId: string,
  asOf?: string,
) {
  return apiGet<CarteraDocuments>(
    `/reports/cartera/${side}/${terceroId}${query({as_of: asOf})}`,
  );
}
