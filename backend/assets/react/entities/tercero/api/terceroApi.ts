import {apiDelete, apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type Tercero = Schema<'TerceroOutput'>;
export type TerceroSummary = Schema<'TerceroSummaryOutput'>;
export type Contact = Schema<'ContactOutput'>;
export type TerceroExport = Schema<'TerceroExportOutput'>;
export type AccountRef = Schema<'AccountRefOutput'>;

/** A page of a list endpoint. */
export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
}

export type Role = 'cliente' | 'proveedor' | 'empleado' | 'otro';

export interface TerceroFilters {
  q?: string;
  role?: Role | '';
  /** '1' only the active ones, '0' only the inactive ones, '' both */
  active?: '1' | '0' | '';
  page?: number;
  per_page?: number;
}

/** What the full form sends (PUT replaces the whole record). */
export interface TerceroPayload {
  person_type: string;
  identification_type: string;
  identification_number: string;
  check_digit: string | null;
  branch_code: string;
  first_names: string | null;
  last_names: string | null;
  business_name: string | null;
  trade_name: string | null;
  city: string | null;
  address: string | null;
  phones: {
    indicative: string;
    number: string;
    extension: string | null;
  }[];
  billing_contact_name: string | null;
  email: string | null;
  mobile: string | null;
  postal_code: string | null;
  vat_regime: string | null;
  billing_contact_is_payer: boolean;
  fiscal_responsibilities: string[];
  roles: string[];
  receivable_account_id: string | null;
  payable_account_id: string | null;
  contacts: {
    id: string | null;
    name: string;
    email: string | null;
    phone: string | null;
  }[];
}

/** What quick-create sends: the essentials a document form asks for. */
export interface QuickTerceroPayload {
  person_type: string;
  identification_type: string;
  identification_number: string;
  check_digit: string | null;
  first_names: string | null;
  last_names: string | null;
  business_name: string | null;
  email: string;
  roles: string[];
}

export function searchTerceros(
  filters: TerceroFilters,
): Promise<Page<TerceroSummary>> {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.role) params.set('role', filters.role);
  if (filters.active) params.set('active', filters.active);
  if (filters.page && filters.page > 1) {
    params.set('page', String(filters.page));
  }
  if (filters.per_page) params.set('per_page', String(filters.per_page));
  const query = params.toString();
  return apiGet<Page<TerceroSummary>>(`/terceros${query ? `?${query}` : ''}`);
}

export function fetchTercero(id: string): Promise<Tercero> {
  return apiGet<Tercero>(`/terceros/${id}`);
}

export async function fetchContacts(id: string): Promise<Contact[]> {
  return (await apiGet<{items: Contact[]}>(`/terceros/${id}/contacts`)).items;
}

export function createTercero(payload: TerceroPayload): Promise<Tercero> {
  return apiPost<Tercero>('/terceros', payload);
}

export function quickCreateTercero(
  payload: QuickTerceroPayload,
): Promise<TerceroSummary> {
  return apiPost<TerceroSummary>('/terceros/quick', payload);
}

export function updateTercero(
  id: string,
  payload: TerceroPayload,
): Promise<Tercero> {
  return apiPut<Tercero>(`/terceros/${id}`, payload);
}

export function deleteTercero(id: string): Promise<null> {
  return apiDelete(`/terceros/${id}`);
}

export function deactivateTercero(id: string): Promise<Tercero> {
  return apiPost<Tercero>(`/terceros/${id}/deactivate`, {});
}

export function reactivateTercero(id: string): Promise<Tercero> {
  return apiPost<Tercero>(`/terceros/${id}/reactivate`, {});
}

/** Ley 1581: the tercero's personal data. */
export function exportTercero(id: string): Promise<TerceroExport> {
  return apiGet<TerceroExport>(`/terceros/${id}/export`);
}

/** Ley 1581: blanks the personal data; cannot be undone. */
export function eraseTercero(id: string): Promise<Tercero> {
  return apiPost<Tercero>(`/terceros/${id}/erase`, {});
}
